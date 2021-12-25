<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\ConfigConstants;
use SOFe\Capital\ParameterizedLabelSelector;

/**
 * Settings related to players as account owners.
 */
final class Config {
    /**
     * @param list<InitialAccount> $initialAccounts The initial accounts created for players.
     * @param list<string> $infoNames The names of the info objects to expose.
     */
    public function __construct(
        public array $initialAccounts,
        public array $infoNames,
    ) {}

    public static function default() : self {
        return new self(
            initialAccounts: [
                new InitialAccount(
                    initialValue: 100,
                    selectorLabels: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{player uuid}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    migrationLabels: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_NAME => "{player name}",
                        ConfigConstants::LABEL_CURRENCY => ConfigConstants::CURRENCY_NAME,
                    ]),
                    initialLabels: new ParameterizedLabelSelector([
                        AccountLabels::VALUE_MIN => "0",
                        AccountLabels::VALUE_MAX => "1000000",
                    ]),
                    overwriteLabels: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_NAME => "{player name}",
                        AccountLabels::PLAYER_INFO_NAME => ConfigConstants::CURRENCY_DEFAULT_INFO,
                    ]),
                )
            ],
            infoNames: [ConfigConstants::CURRENCY_DEFAULT_INFO],
        );
    }
}
