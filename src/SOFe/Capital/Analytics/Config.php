<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\Schema;

final class Config implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /**
     * @param list<SingleCommandSpec> $singleCommands Commands for analytics.
     * @param list<TopCommandSpec> $topCommands Commands for analytics.
     */
    public function __construct(
        public array $singleCommands,
        public array $topCommands,
    ) {}

    public static function fromSingletonArgs(Raw $raw, Schema\Config $config) : self {
        // TODO

        return self::default();
    }

    public static function default() : self {
        return new self(
            singleCommands: [
                new SingleCommandSpec(
                    command: "mymoney",
                    permission: "capital.analytics.self",
                    defaultOpOnly: false,
                    requirePlayer: true,
                    args: [],
                    infos: [
                        "balance" => new Query(
                            target: Query::TARGET_ACCOUNT,
                            selector: new ParameterizedLabelSelector([
                                AccountLabels::PLAYER_UUID => "{sender uuid}",
                            ]),
                            metric: Query::METRIC_ACCOUNT_BALANCE_SUM,
                        ),
                    ],
                    messages: new SingleMessages(
                        main: '{aqua}You have ${balance} in total.',
                    ),
                ),
                new SingleCommandSpec(
                    command: "checkmoney",
                    permission: "capital.analytics.other",
                    defaultOpOnly: true,
                    requirePlayer: false,
                    args: [CommandArgsInfo::ARG_PLAYER],
                    infos: [
                        "balance" => new Query(
                            target: Query::TARGET_ACCOUNT,
                            selector: new ParameterizedLabelSelector([
                                AccountLabels::PLAYER_UUID => "{player uuid}",
                            ]),
                            metric: Query::METRIC_ACCOUNT_BALANCE_SUM,
                        ),
                    ],
                    messages: new SingleMessages(
                        main: '{aqua}{player} has ${balance} in total.',
                    ),
                ),
            ],
            topCommands: [
                new TopCommandSpec(
                    command: "topmoney",
                    permission: "capital.analytics.top",
                    defaultOpOnly: false,
                    requirePlayer: false,
                    args: [],
                    labelSelector: new ParameterizedLabelSelector([]),
                    groupLabels: [AccountLabels::PLAYER_NAME],
                    target: Query::TARGET_ACCOUNT,
                    infos: [
                        "balance" => Query::METRIC_ACCOUNT_BALANCE_SUM,
                    ],
                    orderingInfo: "balance",
                    descending: true,
                    limit: 5,
                    messages: new TopMessages(
                        header: '{gold}Top 5 players:',
                        main: '{aqua}#{rank}: {group1} - ${balance}',
                    ),
                ),
            ],
        );
    }
}
