<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\CapitalException;
use SOFe\Capital\Config\DynamicCommand;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\OracleNames;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\Capital\Plugin\MainClass;
use SOFe\Capital\Schema\Schema;
use SOFe\Capital\TransactionLabels;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

use function abs;
use function array_flip;
use function array_sum;
use function assert;
use function count;
use function is_numeric;
use function round;
use function strpos;
use function substr;

/**
 * Transfer money by running a command.
 */
final class CommandMethod implements Method {
    /**
     * @param DynamicCommand $command The command that invokes this method.
     * @param AccountTarget $src Selects the accounts to take money from.
     * @param AccountTarget $dest Selects the accounts to send money to.
     * @param float $rate The transfer rate. Must be positive. If $rate > 1.0, an extra transaction from `capital/oracle=transfer` to `$dest` is performed. If `0.0 < $rate < 1.0`, only `$rate` of the amount is transferred, and an extra transaction from `$src` to `capital/oracle=transfer` is performed.
     * @param int $minimumAmount The minimum amount to transfer. This should be a non-negative integer.
     * @param int $maximumAmount The maximum amount to transfer. Note that this does not override the original account valueMin/valueMax labels.
     * @param ParameterizedLabelSet<ContextInfo> $transactionLabels The labels set on the transaction.
     * @param Messages $messages The messages to use.
     */
    public function __construct(
        public DynamicCommand $command,
        public AccountTarget $src,
        public AccountTarget $dest,
        public float $rate,
        public int $minimumAmount,
        public ?int $maximumAmount,
        public int $fee,
        public ParameterizedLabelSet $transactionLabels,
        public Messages $messages,
    ) {
    }

    public function register(MainClass $plugin, Capital $api) : void {
        $this->command->register($plugin, function(CommandSender $sender, array $args) use ($api) : void {
            if (!isset($args[1])) {
                throw new InvalidCommandSyntaxException;
            }

            $recipientNamePrefix = $args[0];
            $amountString = $args[1];

            $recipient = Server::getInstance()->getPlayerByPrefix($recipientNamePrefix);
            if ($recipient === null) {
                $sender->sendMessage(KnownTranslationFactory::commands_generic_player_notFound()->prefix(TextFormat::RED));
                return;
            }

            if (!is_numeric($amountString)) {
                throw new InvalidCommandSyntaxException;
            }

            $amount = (int) $amountString;
            if ($amount < $this->minimumAmount) {
                $sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($amountString, (string) $this->minimumAmount)->prefix(TextFormat::RED));
                return;
            }
            if ($this->maximumAmount !== null && $amount > $this->maximumAmount) {
                $sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($amountString, (string) $this->minimumAmount)->prefix(TextFormat::RED));
                return;
            }

            Await::f2c(function() use ($sender, $recipient, $amount, $api) : Generator {
                if ($this->rate > 1.0) {
                    $transferAmount = $amount;
                    $sourceAmount = (int) round($amount * ($this->rate - 1.0));
                    $sinkAmount = 0;
                } else {
                    $transferAmount = (int) round($amount * $this->rate);
                    $sourceAmount = 0;
                    $sinkAmount = $amount - $transferAmount;
                }

                $sinkAmount += $this->fee;

                $info = new ContextInfo(
                    sender: $sender instanceof Player ? new PlayerInfo($sender) : null,
                    recipient: new PlayerInfo($recipient),
                    sentAmount: new NumberInfo((float) ($transferAmount + $sinkAmount)),
                    receivedAmount: new NumberInfo((float) $transferAmount + $sourceAmount),
                );

                $srcLabels = $this->src->getSelector($sender, $recipient);
                if ($srcLabels === null) {
                    if (!$sender instanceof Player) {
                        $sender->sendMessage(InfoAPI::resolve($this->messages->playerOnlyCommand, $info));
                        return;
                    }

                    throw new AssumptionFailedError("AccountTarget::getSelector() must only return null when \$sender is not an instanceof Player.");
                }

                $destLabels = $this->dest->getSelector($sender, $recipient);
                if ($destLabels === null) {
                    if (!$sender instanceof Player) {
                        $sender->sendMessage(InfoAPI::resolve($this->messages->playerOnlyCommand, $info));
                        return;
                    }

                    throw new AssumptionFailedError("AccountTarget::getSelector() must only return null when \$sender is not an instanceof Player.");
                }

                $transactionLabels = $this->transactionLabels->transform($info);

                $srcAccounts = yield from $api->findAccounts($srcLabels);
                $destAccounts = yield from $api->findAccounts($destLabels);

                if (count($srcAccounts) === 0) {
                    $sender->sendMessage(InfoAPI::resolve($this->messages->noSourceAccounts, $info));
                    return;
                }

                if (count($destAccounts) === 0) {
                    $sender->sendMessage(InfoAPI::resolve($this->messages->noDestinationAccounts, $info));
                    return;
                }

                if ($sourceAmount > 0 && $sinkAmount > 0) {
                    $transactionId = Uuid::uuid4();

                    $oracle = yield from $api->getOracle(OracleNames::TRANSFER);
                    $labels2 = [
                        TransactionLabels::TRANSFER_ORACLE => $transactionId->toString(),
                    ];

                    $srcNetChange = -($transferAmount + $sinkAmount); // x
                    $destNetChange = $transferAmount + $sourceAmount; // y

                    if (abs($srcNetChange) < abs($destNetChange)) {
                        // If abs(x) < abs(y), we send x from src to dest, then we send y + x from oracle to dest.
                        $src1 = $srcAccounts[0];
                        $dest1 = $destAccounts[0];
                        $amount1 = -$srcNetChange;

                        $src2 = $oracle;
                        $dest2 = $destAccounts[0];
                        $amount2 = $destNetChange + $srcNetChange;
                    } else {
                        // Else, we send y from src to dest, then we send x + y from src to oracle.
                        $src1 = $srcAccounts[0];
                        $dest1 = $destAccounts[0];
                        $amount1 = $destNetChange;

                        $src2 = $srcAccounts[0];
                        $dest2 = $oracle;
                        $amount2 = $srcNetChange + $destNetChange;
                    }

                    $promise = $api->transact2(
                        $src1, $dest1, $amount1, $transactionLabels,
                        $src2, $dest2, $amount2, $labels2,
                        $transactionId, null
                    );
                } elseif ($sourceAmount > 0 || $sinkAmount > 0) {
                    $transactionId = Uuid::uuid4();

                    $oracle = yield from $api->getOracle(OracleNames::TRANSFER);
                    $labels2 = [
                        TransactionLabels::TRANSFER_ORACLE => $transactionId->toString(),
                    ];

                    if ($sourceAmount > 0) {
                        $src2 = $oracle;
                        $dest2 = $destAccounts[0];
                        $amount2 = $sourceAmount;
                    } else {
                        assert($sinkAmount > 0);
                        $src2 = $srcAccounts[0];
                        $dest2 = $oracle;
                        $amount2 = $sinkAmount;
                    }

                    $promise = $api->transact2(
                        $srcAccounts[0], $destAccounts[0], $transferAmount, $transactionLabels,
                        $src2, $dest2, $amount2, $labels2,
                        $transactionId, null, // we don't need to specify the oracle transaction ID
                    );
                } else {
                    $promise = $api->transact($srcAccounts[0], $destAccounts[0], $transferAmount, $transactionLabels);
                }

                try {
                    yield from $promise;
                } catch (CapitalException $ex) {
                    $error = match ($ex->getCode()) {
                        CapitalException::SOURCE_UNDERFLOW => $this->messages->underflow,
                        CapitalException::DESTINATION_OVERFLOW => $this->messages->underflow,
                        default => $this->messages->internalError,
                    };
                    $sender->sendMessage(InfoAPI::resolve($error, $info));
                    return;
                }

                [$srcValues, $destValues] = yield from Await::all([
                    $api->getBalances($srcAccounts),
                    $api->getBalances($destAccounts),
                ]);

                $successInfo = new SuccessContextInfo(
                    srcBalance: new NumberInfo((float) array_sum($srcValues)),
                    destBalance: new NumberInfo((float) array_sum($destValues)),
                    fallback: $info,
                );

                $sender->sendMessage(InfoAPI::resolve($this->messages->notifySenderSuccess, $successInfo));
                $recipient->sendMessage(InfoAPI::resolve($this->messages->notifyRecipientSuccess, $successInfo));
            });
        });
    }

    public static function parse(Parser $allCommands, Schema $schema, string $commandName, ?DefaultCommand $default) : self {
        if ($commandName === "") {
            $keys = array_flip($allCommands->getKeys());
            $i = 0;

            do {
                $commandName = "invalid-name@$i";
                $i++;
            } while (isset($keys[$commandName]));
        } elseif (($i = strpos($commandName, " ")) !== false) {
            $keys = array_flip($allCommands->getKeys());
            $commandName = $base = substr($commandName, 0, $i);

            $x = 0;
            while (isset($keys[$commandName])) {
                $commandName = "$base@$x";
                $x++;
            }
        }

        $parser = $allCommands->enter($commandName, null);

        $command = DynamicCommand::parse($parser, "transfer", $commandName, $default?->description ?? "(change this)", $default?->defaultOpOnly ?? true);

        $defaultOpOnly = $parser->expectBool("default-op", $default?->defaultOpOnly ?? true, <<<'EOT'
            If set to true, only ops can use this command
            (you can further configure this with permission plugins).
            EOT);

        $src = AccountTarget::parse($parser->enter("src", <<<'EOT'
            The source account to deduct money from.
            EOT), $schema, $default?->src ?? AccountTarget::TARGET_SENDER);

        $dest = AccountTarget::parse($parser->enter("dest", <<<'EOT'
            The destination account to add money to.
            EOT), $schema, $default?->dest ?? AccountTarget::TARGET_RECIPIENT);

        $rate = $parser->expectNumber("rate", $default?->rate ?? 1.0, <<<'EOT'
            The exchange rate, or how much of the original money is sent.
            When using "currency" schema, this allows transferring between
            accounts of different currencies.
            EOT);

        $minimumAmount = $parser->expectInt("minimum-amount", $default?->minimumAmount ?? 0, <<<'EOT'
            The minimum amount of money that can be transferred each time.
            EOT);

        $maximumAmount = $parser->expectNullableInt("maximum-amount", $default?->maximumAmount ?? 0, <<<'EOT'
            The maximum amount of money that can be transferred each time.
            EOT);

        $fee = $parser->expectInt("fee", $default?->fee ?? 0, <<<'EOT'
            This is taken directly out of the source account before money is transferred.
            EOT);

        /** @var ParameterizedLabelSet<ContextInfo> $transactionLabels */
        $transactionLabels = ParameterizedLabelSet::parse($parser->enter("transaction-labels", <<<'EOT'
            These are labels to add to the transaction.
            You can match by these labels to identify how players earn and lose money.
            Labels are formatted using InfoAPI syntax.
            EOT), $default?->transactionLabels ?? []);

        $messages = Messages::parse($parser->enter("messages", null), $default?->messages);

        return new CommandMethod(
            command: $command,
            src: $src,
            dest: $dest,
            rate: $rate,
            minimumAmount: $minimumAmount,
            maximumAmount: $maximumAmount,
            fee: $fee,
            transactionLabels: $transactionLabels,
            messages: $messages,
        );
    }
}
