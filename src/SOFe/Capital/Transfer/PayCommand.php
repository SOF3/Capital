<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\CapitalException;
use SOFe\Capital\Config\DynamicCommand;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSet;
use SOFe\Capital\OracleNames;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\Capital\Plugin\MainClass;
use SOFe\Capital\Schema;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

use function array_shift;
use function is_numeric;
use function round;

/**
 * Transfer money by running a command.
 */
final class PayCommand {
    /**
     * @param DynamicCommand $command The command definition.
     * @param Schema\Schema $schema The schema that selects source and destination accounts.
     * @param float $rate The transfer rate. Must be positive. If $rate > 1.0, an extra transaction from `capital/oracle=transfer` to `$dest` is performed. If `0.0 < $rate < 1.0`, only `$rate` of the amount is transferred, and an extra transaction from `$src` to `capital/oracle=transfer` is performed.
     * @param int $minimumAmount The minimum amount to transfer. This should be a non-negative integer.
     * @param int|null $maximumAmount The maximum amount to transfer. Note that this does not override the original account valueMin/valueMax labels.
     * @param ParameterizedLabelSet<ContextInfo> $transactionLabels The labels set on the transaction.
     * @param Messages $messages The messages to use.
     */
    public function __construct(
        public DynamicCommand $command,
        public Schema\Schema $schema,
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

            $recipientNamePrefix = array_shift($args);
            $amountString = array_shift($args);

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
                $sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooBig($amountString, (string) $this->maximumAmount)->prefix(TextFormat::RED));
                return;
            }

            if (!$sender instanceof Player) {
                $sender->sendMessage($this->messages->playerOnlyCommand);
                return;
            }

            Await::f2c(function() use ($sender, $recipient, $amount, $api, $args) : Generator {
                $srcDeduction = $amount + $this->fee;
                $destAddition = (int) round($amount * $this->rate);

                $info = new ContextInfo(
                    sender: new PlayerInfo($sender),
                    target: new PlayerInfo($recipient),
                    sentAmount: new NumberInfo((float) $srcDeduction),
                    receivedAmount: new NumberInfo((float) $destAddition),
                );

                $schema = yield from Schema\Utils::fromCommand($this->schema, $args, $sender);

                $transactionLabels = $this->transactionLabels->transform($info);


                try {
                    yield from $api->payUnequal(
                        oracleName: OracleNames::TRANSFER,
                        src: $sender,
                        dest: $recipient,
                        schema: $schema,
                        srcDeduction: $srcDeduction,
                        destAddition: $destAddition,
                        directTransactionLabels: $transactionLabels,
                        oracleTransactionLabels: new LabelSet([]),
                        awaitRefresh: true,
                    );
                } catch (CapitalException $ex) {
                    $error = match ($ex->getCode()) {
                        CapitalException::SOURCE_UNDERFLOW => $this->messages->underflow,
                        CapitalException::DESTINATION_OVERFLOW => $this->messages->underflow,
                        CapitalException::EVENT_CANCELLED => $ex->getMessage(),
                        default => $this->messages->internalError,
                    };
                    $sender->sendMessage(InfoAPI::resolve($error, $info));
                    return;
                }

                $sender->sendMessage(InfoAPI::resolve($this->messages->notifySenderSuccess, $info));
                $recipient->sendMessage(InfoAPI::resolve($this->messages->notifyRecipientSuccess, $info));
            });
        });
    }

    public static function parse(Parser $config, Schema\Schema $globalSchema, string $commandName) : self {
        $command = DynamicCommand::parse($config, "transfer", $commandName, "Pays another player", false);

        $schema = $globalSchema->cloneWithConfig($config->enter("selector", "Select which account to pay from and to"));

        $rate = $config->expectNumber("rate", 1.0, <<<'EOT'
            The exchange rate, or how much of the original money is sent.
            When using "currency" schema, this allows transferring between
            accounts of different currencies.
            EOT);

        $minimumAmount = $config->expectInt("minimum-amount", 0, <<<'EOT'
            The minimum amount of money that can be transferred each time.
            EOT);

        $maximumAmount = $config->expectNullableInt("maximum-amount", null, <<<'EOT'
            The maximum amount of money that can be transferred each time.
            Write ~ if the transfer amount is unlimited.
            The actual amount is still subject to the account limits set in the schema section.
            EOT);

        $fee = $config->expectInt("fee", 0, <<<'EOT'
            This is taken directly out of the source account before money is transferred.
            EOT);

        /** @var ParameterizedLabelSet<ContextInfo> $transactionLabels */
        $transactionLabels = ParameterizedLabelSet::parse($config->enter("transaction-labels", <<<'EOT'
            These are labels to add to the transaction.
            You can match by these labels to identify how players earn and lose money.
            Labels are formatted using InfoAPI syntax.
            EOT), [Config::LABEL_PAYMENT => ""]);

        $messages = Messages::parse($config->enter("messages", null), new Messages(
                    playerOnlyCommand: '{red}Only players may use this command.',
                    notifySenderSuccess: '{green}You have sent ${sentAmount} to {target}. You now have ${sender money} left.',
                    notifyRecipientSuccess: '{green}You have received ${receivedAmount} from {sender}. You now have ${target money} left.',
                    underflow: '{red}You do not have ${sentAmount}.',
                    overflow: '{red}The accounts of {target} are full. They cannot fit in ${sentAmount} more.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ));

        return new self(
            command: $command,
            schema: $schema,
            rate: $rate,
            minimumAmount: $minimumAmount,
            maximumAmount: $maximumAmount,
            fee: $fee,
            transactionLabels: $transactionLabels,
            messages: $messages,
        );
    }
}
