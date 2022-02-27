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
use SOFe\Capital\OracleNames;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\Capital\Plugin\MainClass;
use SOFe\Capital\Schema;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

use function array_shift;
use function is_numeric;

/**
 * Transfer money by running a command.
 */
final class AlterCommand {
    public const TYPE_ADD_MONEY = "add-money";
    public const TYPE_TAKE_MONEY = "take-money";

    /**
     * @param DynamicCommand $command The command definition.
     * @param self::TYPE_* $type The direction of the transaction.
     * @param Schema\Schema $schema The schema that selects the altered account.
     * @param int $minimumAmount The minimum amount to transfer. This should be a non-negative integer.
     * @param int|null $maximumAmount The maximum amount to transfer. Note that this does not override the original account valueMin/valueMax labels.
     * @param ParameterizedLabelSet<ContextInfo> $transactionLabels The labels set on the transaction.
     * @param Messages $messages The messages to use.
     */
    public function __construct(
        public DynamicCommand $command,
        public string $type,
        public Schema\Schema $schema,
        public int $minimumAmount,
        public ?int $maximumAmount,
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

            Await::f2c(function() use ($sender, $recipient, $amount, $api, $args) : Generator {
                $info = new ContextInfo(
                    sender: $sender instanceof Player ? new PlayerInfo($sender) : null,
                    target: new PlayerInfo($recipient),
                    sentAmount: new NumberInfo((float) $amount),
                    receivedAmount: new NumberInfo((float) $amount),
                );

                $schema = yield from Schema\Utils::fromCommand($this->schema, $args, $sender);

                $transactionLabels = $this->transactionLabels->transform($info);

                $promise = match ($this->type) {
                    self::TYPE_ADD_MONEY => $api->addMoney(
                        oracleName: OracleNames::TRANSFER,
                        player: $recipient,
                        schema: $schema,
                        amount: $amount,
                        transactionLabels: $transactionLabels,
                        awaitRefresh: true,
                    ),
                    self::TYPE_TAKE_MONEY => $api->takeMoney(
                        oracleName: OracleNames::TRANSFER,
                        player: $recipient,
                        schema: $schema,
                        amount: $amount,
                        transactionLabels: $transactionLabels,
                        awaitRefresh: true,
                    ),
                };

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

                $sender->sendMessage(InfoAPI::resolve($this->messages->notifySenderSuccess, $info));
                $recipient->sendMessage(InfoAPI::resolve($this->messages->notifyRecipientSuccess, $info));
            });
        });
    }

    public static function parse(Parser $config, Schema\Schema $schema, string $commandName, bool $addMoney) : self {
        $command = DynamicCommand::parse($config, "transfer", $commandName, $addMoney ? "Add money to a player" : "Remove money from a player", true);

        $minimumAmount = $config->expectInt("minimum-amount", 0, <<<'EOT'
            The minimum amount of money that can be transferred each time.
            EOT);

        $maximumAmount = $config->expectNullableInt("maximum-amount", null, <<<'EOT'
            The maximum amount of money that can be transferred each time.
            EOT);

        /** @var ParameterizedLabelSet<ContextInfo> $transactionLabels */
        $transactionLabels = ParameterizedLabelSet::parse($config->enter("transaction-labels", <<<'EOT'
            These are labels to add to the transaction.
            You can match by these labels to identify how players earn and lose money.
            Labels are formatted using InfoAPI syntax.
            EOT), [Config::LABEL_OPERATOR => ""]);

        $messages = Messages::parse($config->enter("messages", null), new Messages(
                    playerOnlyCommand: '{red}Only players may use this command.',
                    notifySenderSuccess: $addMoney ? '{green}{target} has received ${receivedAmount}. They now have ${target money} left.' : '{green}You have taken ${receivedAmount} from {target}. They now have ${target money} left.',
                    notifyRecipientSuccess: $addMoney ? '{green}You have received ${receivedAmount}. You now have ${target money} left.' : '{green}An admin took ${sentAmount} from you. You now have ${target money} left.',
                    underflow: $addMoney ? '{red}An internal error occurred.' : '{red}{target} does not have ${sentAmount}.',
                    overflow: $addMoney ? '{red}{target} cannot fit ${receivedAmount} more money.' : '{red}An internal error occurred.',
                    internalError: '{red}An internal error occurred. Please try again.',
        ));

        return new self(
            command: $command,
            type: $addMoney ? selF::TYPE_ADD_MONEY : self::TYPE_TAKE_MONEY,
            schema: $schema,
            minimumAmount: $minimumAmount,
            maximumAmount: $maximumAmount,
            transactionLabels: $transactionLabels,
            messages: $messages,
        );
    }
}
