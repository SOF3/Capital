<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\ConfigConstants;
use SOFe\Capital\OracleNames;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\ParameterizedLabelSet;

final class TransferConfig {
    /**
     * @param list<TransferMethod> $transferMethods Methods to initiate money transfer between accounts.
     */
    public function __construct(
        public array $transferMethods,
    ) {}

    public static function default() : self {
        return new self(
            [
                new CommandTransferMethod(
                    "pay",
                    "capital.transfer.pay",
                    new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{sender uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{recipient uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    1.0,
                    0,
                    10000,
                    new ParameterizedLabelSet([
                        ConfigConstants::LABEL_PAYMENT => "",
                    ]),
                ),
                new CommandTransferMethod(
                    "takemoney",
                    "capital.transfer.takemoney",
                    new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{recipient uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    new ParameterizedLabelSelector([
                        AccountLabels::ORACLE => OracleNames::TRANSFER,
                    ]),
                    1.0,
                    0,
                    1000000,
                    new ParameterizedLabelSet([
                        ConfigConstants::LABEL_OPERATOR => "",
                    ]),
                ),
                new CommandTransferMethod(
                    "addmoney",
                    "capital.transfer.addmoney",
                    new ParameterizedLabelSelector([
                        AccountLabels::ORACLE => OracleNames::TRANSFER,
                    ]),
                    new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{recipient uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    1.0,
                    0,
                    1000000,
                    new ParameterizedLabelSet([
                        ConfigConstants::LABEL_OPERATOR => "",
                    ]),
                ),
            ],
        );
    }
}
