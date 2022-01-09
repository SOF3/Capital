<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Generator;
use pocketmine\plugin\PluginBase;
use PrefixedLogger;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\ModInterface;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonTrait;

use function array_reverse;
use function file_put_contents;
use function substr;
use function yaml_emit;

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
        Schema\Mod::class,
        Database\Mod::class,
        Cache\Mod::class,
        Player\Mod::class,
        Transfer\Mod::class,
        Analytics\Mod::class,
    ];

    public static Context $context;

    protected function onEnable() : void {
        $context = new Context(new PrefixedLogger($this->getLogger(), "Di"));
        $context->store(AwaitStd::init($this));
        $context->store($this);

        self::$context = $context;

        Await::f2c(function() use($context) : Generator {
            foreach(self::MODULES as $module) {
                $this->getLogger()->debug("Loading " . substr($module, 0, -4));
                yield from $module::init($context);
            }

            $context->call(function(MainClass $main, Config\Raw $raw) {
                if($raw->saveConfig !== null) {
                    file_put_contents($main->getDataFolder() . "config.yml", yaml_emit($raw->saveConfig));
                }
            });
        });
    }

    protected function onDisable() : void {
        $context = self::$context;
        foreach(array_reverse(self::MODULES) as $module) {
            $module::shutdown($context);
        }
    }
}
