<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\ParameterizedLabelSelector;

final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /**
     * @param list<SingleCommandSpec> $singleCommands Commands for analytics.
     * @param list<TopCommandSpec> $topCommands Commands for analytics.
     */
    public function __construct(
        public array $singleCommands,
        public array $topCommands,
    ) {}

    public static function parse(Parser $config, Context $di) : Generator{
        false && yield;

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
