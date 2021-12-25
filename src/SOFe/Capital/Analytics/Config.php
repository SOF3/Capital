<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\ParameterizedLabelSelector;

final class Config {
    /**
     * @param list<CommandSpec> $commands Commands for analytics.
     */
    public function __construct(
        public array $commands,
    ) {}

    public static function default() : self {
        return new self(
            commands: [
                new CommandSpec(
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
                    topN: null,
                    messages: new Messages(
                        main: '{aqua}You have ${balance} in total.',
                    ),
                ),
                new CommandSpec(
                    command: "checkmoney",
                    permission: "capital.analytics.other",
                    defaultOpOnly: true,
                    requirePlayer: false,
                    args: [CommandSpec::ARG_PLAYER],
                    infos: [
                        "balance" => new Query(
                            target: Query::TARGET_ACCOUNT,
                            selector: new ParameterizedLabelSelector([
                                AccountLabels::PLAYER_UUID => "{player uuid}",
                            ]),
                            metric: Query::METRIC_ACCOUNT_BALANCE_SUM,
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
