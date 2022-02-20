<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use SOFe\Capital\Capital;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\OracleNames;
use SOFe\Capital\Plugin\MainClass;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    /**
     * @return Generator<mixed, mixed, mixed, self>
     */
    public static function fromSingletonArgs(Config $config, MainClass $plugin, Capital $api) : Generator {
        yield from $api->getOracle(OracleNames::TRANSFER);

        ContextInfo::init();
        SuccessContextInfo::init();

        foreach ($config->commands as $command) {
            $command->register($plugin, $api);
        }

        return new self;
    }
}
