<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Generator;
use pocketmine\plugin\PluginBase;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\ModInterface;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonTrait;

use function array_reverse;

final class MainClass extends PluginBase implements Singleton {
    use SingletonTrait;

    public static function getStd(Context $context) : AwaitStd {
        /** @var AwaitStd $std */
        $std = $context->fetchClass(AwaitStd::class);
        return $std;
    }

    /** @var list<class-string<ModInterface>> */
    public const MODULES = [
        Config\Mod::class,
        Database\Mod::class,
        Cache\Mod::class,
        Player\Mod::class,
        Transfer\Mod::class,
        Analytics\Mod::class,
    ];

    public static Context $context;

    protected function onEnable() : void {
        $context = new Context;
        $context->store(AwaitStd::init($this));
        $context->store($this);

        self::$context = $context;

        Await::f2c(static function() use($context) : Generator {
            foreach(self::MODULES as $module) {
                yield from $module::init($context);
            }
        });
    }

    protected function onDisable() : void {
        $context = self::$context;
        foreach(array_reverse(self::MODULES) as $module) {
            $module::shutdown($context);
        }
    }
}
