<?php

declare(strict_types=1);

namespace SOFe\Capital\Loader;

use Generator;
use InvalidArgumentException;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital as C;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;

use SOFe\Capital\Di\SingletonTrait;
use function is_subclass_of;

final class Loader implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.2.0";

    /** @var array<class-string<Singleton>, true> */
    private static array $extraEntryPoints = [];

    /**
     * @param class-string<Singleton> $class
     */
    public static function addEntryPoint(string $class) : void {
        if (!is_subclass_of($class, Singleton::class)) {
            throw new InvalidArgumentException("Entry point must be a subclass of " . Singleton::class);
        }
        self::$extraEntryPoints[$class] = true;
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
        $all = [];
        foreach (self::$extraEntryPoints as $entryPoint => $_) {
            $all[] = $entryPoint::get($context);
        }
        yield from Await::all($all);

        return new self;
    }
}
