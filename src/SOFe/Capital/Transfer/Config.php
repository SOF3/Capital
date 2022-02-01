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
use SOFe\Capital\ParameterizedLabelSet;

use function array_filter;
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
            These slash commands initiate transfers.
            If "default-op" is set to true, users must be OP'ed.
            EOT);

        $commandNames = array_filter($commandsParser->getKeys(), fn($command) => $command[0] !== "#");

        if (count($commandNames) === 0) {
            $commandsParser->failSafe(null, "There must be at least one method");
            MethodFactory::buildCommand($commandsParser, new CommandMethod(
                command: "pay",
                permission: "capital.transfer.pay",
                defaultOpOnly: false,
                src: CommandMethod::TARGET_SENDER,
                dest: CommandMethod::TARGET_RECIPIENT,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: 10000,
                transactionLabels: new ParameterizedLabelSet([
                    Constants::LABEL_PAYMENT => "",
                ]),
                messages: new Messages(
                    notifySenderSuccess: '{green}You have sent ${sentAmount} to ${recipient}. You now have ${srcBalance} left.',
                    notifyRecipientSuccess: '{green}You have received ${receivedAmount} from ${sender}. You now have ${destBalance} left.',
                    noSourceAccounts: '{red}There are no accounts to send money from.',
                    noDestinationAccounts: '{red}There are no accounts to send money to.',
                    underflow: '{red}You do not have ${sentAmount}.',
                    overflow: '{red}The accounts of {recipient} are full. They cannot fit in ${sentAmount} more.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ),
            ));
            MethodFactory::buildCommand($commandsParser, new CommandMethod(
                command: "takemoney",
                permission: "capital.transfer.takemoney",
                defaultOpOnly: true,
                src: CommandMethod::TARGET_RECIPIENT,
                dest: CommandMethod::TARGET_SYSTEM,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: 1000000,
                transactionLabels: new ParameterizedLabelSet([
                    Constants::LABEL_OPERATOR => "",
                ]),
                messages: new Messages(
                    notifySenderSuccess: '{green}You have taken ${sentAmount} from {recipient}. They now have ${destBalance} left.',
                    notifyRecipientSuccess: '{green}An admin took ${sentAmount} from you. You now have ${destBalance} left.',
                    noSourceAccounts: '{red}There are no accounts to send money from.',
                    noDestinationAccounts: '{red}An internal error occurred.',
                    underflow: '{red}{recipient} does not have ${sentAmount} to be taken.',
                    overflow: '{red}An internal error occurred.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ),
            ));
            MethodFactory::buildCommand($commandsParser, new CommandMethod(
                command: "addmoney",
                permission: "capital.transfer.addmoney",
                defaultOpOnly: true,
                src: CommandMethod::TARGET_SYSTEM,
                dest: CommandMethod::TARGET_RECIPIENT,
                rate: 1.0,
                minimumAmount: 0,
                maximumAmount: 1000000,
                transactionLabels: new ParameterizedLabelSet([
                    Constants::LABEL_OPERATOR => "",
                ]),
                messages: new Messages(
                    notifySenderSuccess: '{green}{recipient} has received ${receivedAmount}. They now have ${destBalance} left.',
                    notifyRecipientSuccess: '{green}You have received ${receivedAmount}. You now have ${destBalance} left.',
                    noSourceAccounts: '{red}An internal error occurred.',
                    noDestinationAccounts: '{red}There are no accounts to send money to.',
                    underflow: '{red}An internal error occurred.',
                    overflow: '{red}The accounts of {recipient} are full. They cannot fit in ${sentAmount} more.',
                    internalError: '{red}An internal error occurred. Please try again.',
                ),
            ));
            $commandNames = array_filter($commandsParser->getKeys(), fn($command) => $command[0] !== "#");
        }

        $methods = [];
        foreach ($commandNames as $command) {
            $commandParser = $commandsParser->enter($command, "");
            $methods[] = MethodFactory::buildCommand($commandParser);
        }

        return new self($methods);
    }
}
