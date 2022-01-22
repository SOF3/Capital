<?php

declare(strict_types=1);

namespace SOFe\Capital\Loader;

use SOFe\Capital as C;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;

use SOFe\Capital\Di\SingletonTrait;

final class Loader implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(
        C\Config\Mod $config,
        C\Schema\Mod $schema,
        C\Database\Mod $database,
        C\Cache\Mod $cache,
        C\Transfer\Mod $transfer,
        C\Analytics\Mod $analytics,
    ) : self {
        return new self;
    }
}
