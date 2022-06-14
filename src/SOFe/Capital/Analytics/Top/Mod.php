<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Top;

use Generator;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Analytics;
use SOFe\Capital\Analytics\PaginationInfo;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use SOFe\InfoAPI\Info;

use function bin2hex;
use function random_bytes;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.2.0";

    public static function fromSingletonArgs(Analytics\Config $config, MainClass $plugin, AwaitStd $std, DatabaseUtils $dbu) : self {
        Info::registerByReflection("capital.analytics.top", PaginationInfo::class);
        ResultEntryInfo::initCommon();

        foreach ($config->topQueries as $query) {
            $query->register($plugin, $std, $dbu);
        }

        return new self;
    }

    /**
     * @return VoidPromise
     */
    public static function runRefreshLoop(QueryArgs $query, RefreshArgs $refresh, AwaitStd $std, DatabaseUtils $database) : Generator {
        while (true) {
            yield from $std->sleep(($refresh->batchFrequency * 20));

            $runId = bin2hex(random_bytes(16));

            yield from $database->collect($runId, $query, $refresh->expiry, $refresh->batchSize);

            yield from $database->compute($runId, $query);
        }
    }
}
