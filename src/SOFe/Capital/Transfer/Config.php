<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Schema\Config as SchemaConfig;


final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /** The label applied on normal payment transactions. */
    public const LABEL_PAYMENT = "payment";

    /** The label applied on operator-related transactions. */
    public const LABEL_OPERATOR = "operator";

    /**
     * @param list<Command> $commands Commands to initiate money transfer between accounts.
     */
    public function __construct(
        public array $commands,
    ) {
    }

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        false && yield;

        $transferParser = $config->enter("transfer", <<<'EOT'
            "transfer" tells Capital what methods admins and players can send money through.
            EOT);

        $commandsParser = $transferParser->enter("commands", <<<'EOT'
            These commands initiate transfers.
            EOT, $isNew);

        $commandNames = $commandsParser->getKeys();

        /** @var SchemaConfig $schemaConfig */
        $schemaConfig = yield from $raw->awaitConfigInternal(SchemaConfig::class);
        $schema = $schemaConfig->schema;

        if ($isNew) {
            Command::parse($commandsParser, $schema, "pay", new DefaultCommand(
                description: "Pays another player.",
                defaultOpOnly: false,
                src: AccountTarget::TARGET_SENDER,
                dest: AccountTarget::TARGET_RECIPIENT,
                rate: 1.0,
                fee: 0,
                minimumAmount: 0,
                maximumAmount: 1000000,
                transactionLabels: [
                    Config::LABEL_PAYMENT => "",
                ],
                messages: new Messages(
                    playerOnlyCommand: '{red}Only players may use this command.',
                    notifySenderSuccess: '{green}You have sent ${sentAmount} to ${recipient}. You now have ${srcBalance} left.',
                    notifyRecipientSuccess: '{green}You have received ${receivedAmount} from ${sender}. You now have ${destBalance} left.',
                    noSourceAccounts: '{red}There are no accounts to send money from.',
                    noDestinationAccounts: '{red}There are no accounts to send money to.',
                    underflow: '{red}You do not have ${sentAmount}.',
                    overflow: '{red}The accounts of {recipient} are full. They cannot fit in ${sentAmount} more.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ),
            ));
            Command::parse($commandsParser, $schema, "takemoney", new DefaultCommand(
                description: "Reduce a player's money.",
                defaultOpOnly: true,
                src: AccountTarget::TARGET_RECIPIENT,
                dest: AccountTarget::TARGET_SYSTEM,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: null,
                fee: 0,
                transactionLabels: [
                    Config::LABEL_OPERATOR => "",
                ],
                messages: new Messages(
                    playerOnlyCommand: '{red}Only players may use this command.',
                    notifySenderSuccess: '{green}You have taken ${receivedAmount} from {recipient}. They now have ${srcBalance} left.',
                    notifyRecipientSuccess: '{green}An admin took ${sentAmount} from you. You now have ${srcBalance} left.',
                    noSourceAccounts: '{red}There are no accounts to send money from.',
                    noDestinationAccounts: '{red}An internal error occurred.',
                    underflow: '{red}{recipient} does not have ${sentAmount} to be taken.',
                    overflow: '{red}An internal error occurred.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ),
            ));
            Command::parse($commandsParser, $schema, "addmoney", new DefaultCommand(
                description: "Adds a player's money.",
                defaultOpOnly: true,
                src: AccountTarget::TARGET_SYSTEM,
                dest: AccountTarget::TARGET_RECIPIENT,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: null,
                fee: 0,
                transactionLabels: [
                    Config::LABEL_OPERATOR => "",
                ],
                messages: new Messages(
                    playerOnlyCommand: '{red}Only players may use this command.',
                    notifySenderSuccess: '{green}{recipient} has received ${receivedAmount}. They now have ${destBalance} left.',
                    notifyRecipientSuccess: '{green}You have received ${receivedAmount}. You now have ${destBalance} left.',
                    noSourceAccounts: '{red}An internal error occurred.',
                    noDestinationAccounts: '{red}There are no accounts to send money to.',
                    underflow: '{red}An internal error occurred.',
                    overflow: '{red}The accounts of {recipient} are full. They cannot fit in ${sentAmount} more.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ),
            ));
            $commandNames = $commandsParser->getKeys();
        }

        $commands = [];
        foreach ($commandNames as $commandName) {
            $commands[] = Command::parse($commandsParser, $schema, $commandName, null);
        }

        return new self($commands);
    }
}
