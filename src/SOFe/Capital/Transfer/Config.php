<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\ConfigConstants;
use SOFe\Capital\OracleNames;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\ParameterizedLabelSet;

final class Config {
    /**
     * @param list<Method> $transferMethods Methods to initiate money transfer between accounts.
     */
    public function __construct(
        public array $transferMethods,
    ) {}

    public static function default() : self {
        return new self(
            transferMethods: [
                new CommandMethod(
                    command: "pay",
                    permission: "capital.transfer.pay",
                    defaultOpOnly: false,
                    src: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{sender uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    dest: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{recipient uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    rate: 1.0,
                    minimumAmount: 0,
                    maximumAmount: 10000,
                    transactionLabels: new ParameterizedLabelSet([
                        ConfigConstants::LABEL_PAYMENT => "",
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
                ),
                new CommandMethod(
                    command: "takemoney",
                    permission: "capital.transfer.takemoney",
                    defaultOpOnly: true,
                    src: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{recipient uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    dest: new ParameterizedLabelSelector([
                        AccountLabels::ORACLE => OracleNames::TRANSFER,
                    ]),
                    rate: 1.0,
                    minimumAmount: 0,
                    maximumAmount: 1000000,
                    transactionLabels: new ParameterizedLabelSet([
                        ConfigConstants::LABEL_OPERATOR => "",
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
                ),
                new CommandMethod(
                    command: "addmoney",
                    permission: "capital.transfer.addmoney",
                    defaultOpOnly: true,
                    src: new ParameterizedLabelSelector([
                        AccountLabels::ORACLE => OracleNames::TRANSFER,
                    ]),
                    dest: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{recipient uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    rate: 1.0,
                    minimumAmount: 0,
                    maximumAmount: 1000000,
                    transactionLabels: new ParameterizedLabelSet([
                        ConfigConstants::LABEL_OPERATOR => "",
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
                ),
            ],
        );
    }
}
