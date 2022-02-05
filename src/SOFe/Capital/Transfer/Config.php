<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Constants;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Schema\Config as SchemaConfig;

use function count;

final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /**
     * @param list<Method> $transferMethods Methods to initiate money transfer between accounts.
     */
    public function __construct(
        public array $transferMethods,
    ) {}

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        false && yield;

        $transferParser = $config->enter("transfer", <<<'EOT'
            "transfer" tells Capital what methods admins and players can send money through.
            EOT);

        $commandsParser = $transferParser->enter("commands", <<<'EOT'
            These commands initiate transfers.
            EOT);

        $commandNames = $commandsParser->getKeys();

        /** @var SchemaConfig $schemaConfig */
        $schemaConfig = yield from $raw->awaitConfigInternal(SchemaConfig::class);
        $schema = $schemaConfig->schema;

        if (count($commandNames) === 0) {
            $commandsParser->failSafe(null, "There must be at least one method");
            CommandMethod::parse($commandsParser, $schema, new DefaultCommand(
                command: "pay",
                permission: "capital.transfer.pay",
                defaultOpOnly: false,
                src: AccountTarget::TARGET_SENDER,
                dest: AccountTarget::TARGET_RECIPIENT,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: 10000,
                transactionLabels: [
                    Constants::LABEL_PAYMENT => "",
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
            CommandMethod::parse($commandsParser, $schema, new DefaultCommand(
                command: "takemoney",
                permission: "capital.transfer.takemoney",
                defaultOpOnly: true,
                src: AccountTarget::TARGET_RECIPIENT,
                dest: AccountTarget::TARGET_SYSTEM,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: 1000000,
                transactionLabels: [
                    Constants::LABEL_OPERATOR => "",
                ],
                messages: new Messages(
                    playerOnlyCommand: '{red}Only players may use this command.',
                    notifySenderSuccess: '{green}You have taken ${sentAmount} from {recipient}. They now have ${destBalance} left.',
                    notifyRecipientSuccess: '{green}An admin took ${sentAmount} from you. You now have ${destBalance} left.',
                    noSourceAccounts: '{red}There are no accounts to send money from.',
                    noDestinationAccounts: '{red}An internal error occurred.',
                    underflow: '{red}{recipient} does not have ${sentAmount} to be taken.',
                    overflow: '{red}An internal error occurred.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ),
            ));
            CommandMethod::parse($commandsParser, $schema, new DefaultCommand(
                command: "addmoney",
                permission: "capital.transfer.addmoney",
                defaultOpOnly: true,
                src: AccountTarget::TARGET_SYSTEM,
                dest: AccountTarget::TARGET_RECIPIENT,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: 1000000,
                transactionLabels: [
                    Constants::LABEL_OPERATOR => "",
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

        $methods = [];
        foreach ($commandNames as $command) {
            $commandParser = $commandsParser->enter($command, "");
            $methods[] = CommandMethod::parse($commandParser, $schema);
        }

        return new self($methods);
    }
}
