<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Single;

use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Analytics;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(Analytics\Config $config, MainClass $plugin, AwaitStd $std, Database $db) : self {
        foreach ($config->singleQueries as $manager) {
            $manager->register($plugin, $std, $db);
        }

        return new self;
    }
}
