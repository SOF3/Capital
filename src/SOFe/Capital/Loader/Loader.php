<?php

declare(strict_types=1);

namespace SOFe\Capital\Loader;

use Generator;
use InvalidArgumentException;
use SOFe\Capital as C;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;

use SOFe\Capital\Di\SingletonTrait;

final class Loader implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.2.0";

    private static array $extraEntryPoints = [];

    public static function addEntryPoint(string $class) : void {
        if(!is_subclass_of($class, Singleton::class)) {
            throw new InvalidArgumentException("Entry point must be a subclass of " . Singleton::class);
        }
    }

    public static function fromSingletonArgs(
        C\Config\Mod $config,
        C\Schema\Mod $schema,
        C\Database\Mod $database,
        C\Transfer\Mod $transfer,
        C\Analytics\Mod $analytics,
        C\Migration\Mod $migration,
        C\Di\Context $context,
    ) : Generator {
        foreach(self::$extraEntryPoints as $entryPoint) {
            yield from $context->fetchClass($entryPoint);
        }

        return new self;
    }
}
