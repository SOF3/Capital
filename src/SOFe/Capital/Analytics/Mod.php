<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use SOFe\InfoAPI\Info;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(Config $config, MainClass $plugin, AwaitStd $std, Database $db, DatabaseUtils $dbu) : self {
        Info::registerByReflection("capital.analytics.top", PaginationInfo::class);
        TopResultEntryInfo::initCommon();

        foreach ($config->singleQueries as $manager) {
            $manager->register($plugin, $std, $db);
        }

        foreach ($config->infoCommands as $cmd) {
            $cmd->register($plugin);
        }

        foreach ($config->topQueries as $query) {
            $query->register($plugin, $std, $dbu);
        }

        return new self;
    }
}
