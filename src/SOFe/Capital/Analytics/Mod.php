<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(Config $config, MainClass $plugin, Single\Mod $_single, Top\Mod $_top) : self {
        foreach ($config->infoCommands as $cmd) {
            $cmd->register($plugin);
        }

        return new self;
    }
}
