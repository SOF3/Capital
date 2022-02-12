<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

/**
 * Settings related to players as account owners.
 */
final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    public function __construct(
        public int $accountBalanceRefreshInterval,
        public int $labelSetRefreshInterval,
        public int $selectorMatchRefreshInterval,
    ) {
    }

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        false && yield;

        $config = $config->enter("cache", <<<'EOT'
            The cache synchronizes data from the database for faster retrieval.

            Other plugins may require you to enable some of the cache here.
            Avoid modifying these settings unless another plugin asks you to do this.

            Generally speaking, the cache is only used for display purposes,
            so setting long refresh frequencies will not lead to data inconsistency.
            Refresh may not be necessary if there are no other servers modifying the same database.
            EOT);

        $accountBalanceRefreshInterval = (int) ($config->expectNumber(
            "account-balance-refresh-interval",
            5.0,
            <<<'EOT'
            The interval in seconds to refresh account balance.
            Set to 0 to disable refresh.

            Long refresh frequencies may lead to latent data in displays like scoreboards.
            EOT,
        ) * 20.0);

        $labelSetRefreshInterval = (int) ($config->expectNumber(
            "label-set-refresh-interval",
            0,
            <<<'EOT'
            The interval in seconds to refresh labels of cached accounts.
            Set to 0 to disable refresh.

            Account labels are unlikely to update externally,
            so it is ok to disable this refresh.
            EOT,
        ) * 20.0);

        $selectorMatchRefreshInterval = (int) ($config->expectNumber(
            "selector-match-refresh-interval",
            0,
            <<<'EOT'
            The interval in seconds to refresh selector matches.
            Set to 0 to disable refresh.
            EOT,
        ) * 20.0);

        return new self(
            accountBalanceRefreshInterval: $accountBalanceRefreshInterval,
            labelSetRefreshInterval: $labelSetRefreshInterval,
            selectorMatchRefreshInterval: $selectorMatchRefreshInterval,
        );
    }
}
