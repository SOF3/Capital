<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\ParameterizedLabelSelector;

final class AnalyticsConfig {
    /**
     * @param list<AnalyticsCommandSpec> $commands Commands for analytics.
     */
    public function __construct(
        public array $commands,
    ) {}

    public static function default() : self {
        return new self(
            commands: [
                new AnalyticsCommandSpec(
                    command: "mymoney",
                    permission: "capital.analytics.self",
                    defaultOpOnly: false,
                    requirePlayer: true,
                    args: [],
                    infos: [
                        "balance" => new AnalyticsQuery(
                            target: AnalyticsQuery::TARGET_ACCOUNT,
                            selector: new ParameterizedLabelSelector([
                                AccountLabels::PLAYER_UUID => "{sender uuid}",
                            ]),
                            metric: AnalyticsQuery::METRIC_ACCOUNT_BALANCE_SUM,
                        ),
                    ],
                    topN: null,
                    messages: new Messages(
                        main: '{aqua}You have ${balance} in total.',
                    ),
                ),
                new AnalyticsCommandSpec(
                    command: "checkmoney",
                    permission: "capital.analytics.other",
                    defaultOpOnly: true,
                    requirePlayer: false,
                    args: [AnalyticsCommandSpec::ARG_PLAYER],
                    infos: [
                        "balance" => new AnalyticsQuery(
                            target: AnalyticsQuery::TARGET_ACCOUNT,
                            selector: new ParameterizedLabelSelector([
                                AccountLabels::PLAYER_UUID => "{player uuid}",
                            ]),
                            metric: AnalyticsQuery::METRIC_ACCOUNT_BALANCE_SUM,
                        ),
                    ],
                    topN: null,
                    messages: new Messages(
                        main: '{aqua}{player} has ${balance} in total.',
                    ),
                ),
            ],
        );
    }
}
