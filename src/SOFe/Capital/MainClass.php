<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Generator;
use pocketmine\plugin\PluginBase;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;

use function array_reverse;

final class MainClass extends PluginBase implements Singleton {
    use SingletonTrait;

    /** @var list<class-string<ModInterface>> */
    public const MODULES = [
        Database\Mod::class,
        Cache\Mod::class,
        Player\Mod::class,
        Transfer\Mod::class,
    ];

    public static TypeMap $typeMap;

    protected function onEnable(): void {
        $typeMap = new TypeMap;
        self::$typeMap = $typeMap;

        $typeMap->store(AwaitStd::init($this));
        $typeMap->store($this);
        $typeMap->store(Config::load($typeMap));

        Await::f2c(static function() use($typeMap) : Generator {
            foreach(self::MODULES as $module) {
                yield from $module::init($typeMap);
            }
        });
    }

    protected function onDisable(): void {
        $typeMap = self::$typeMap;
        foreach(array_reverse(self::MODULES) as $module) {
            $module::shutdown($typeMap);
        }
    }
}
