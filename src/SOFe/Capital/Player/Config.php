<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Constants;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\ParameterizedLabelSelector;

/**
 * Settings related to players as account owners.
 */
final class Config implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /**
     * @param list<InitialAccount> $initialAccounts The initial accounts created for players.
     * @param list<string> $infoNames The names of the info objects to expose.
     */
    public function __construct(
        public array $initialAccounts,
        public array $infoNames,
    ) {}

    public static function fromSingletonArgs() : self {
        return new self(
            initialAccounts: [
                new InitialAccount(
                    initialValue: 100,
                    selectorLabels: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_UUID => "{player uuid}",
                        Constants::LABEL_CURRENCY => Constants::CURRENCY_NAME,
                    ]),
                    migrationLabels: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_NAME => "{player name}",
                        Constants::LABEL_CURRENCY => Constants::CURRENCY_NAME,
                    ]),
                    initialLabels: new ParameterizedLabelSelector([
                        AccountLabels::VALUE_MIN => "0",
                        AccountLabels::VALUE_MAX => "1000000",
                    ]),
                    overwriteLabels: new ParameterizedLabelSelector([
                        AccountLabels::PLAYER_NAME => "{player name}",
                        AccountLabels::PLAYER_INFO_NAME => Constants::CURRENCY_DEFAULT_INFO,
                    ]),
                )
            ],
            infoNames: [Constants::CURRENCY_DEFAULT_INFO],
        );
    }
}
