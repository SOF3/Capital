<?php

declare(strict_types=1);

namespace SOFe\Capital\Plugin;

use Generator;
use pocketmine\plugin\PluginBase;
use PrefixedLogger;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Loader\Loader;

use function getenv;

final class MainClass extends PluginBase implements Singleton {
    use SingletonTrait;

    public static function getStd(Context $context) : AwaitStd {
        /** @var AwaitStd $std */
        $std = $context->fetchClass(AwaitStd::class);
        return $std;
    }

    public static Context $context;

    private bool $debug;

    protected function onEnable() : void {
        $this->debug = getenv("CAPITAL_DEBUG") === "1";

        $context = new Context(new PrefixedLogger($this->getLogger(), Context::class));
        $context->store(AwaitStd::init($this));
        $context->store($this);

        self::$context = $context;

        Await::f2c(function() use ($context) : Generator {
            yield from self::getStd($context)->sleep(0);

            yield from Loader::get($context);

            if ($this->debug) {
                $context->getDepGraph()->write($this->getDataFolder() . "depgraph.dot");
            }
        });
    }

    protected function onDisable() : void {
        $context = self::$context;
        $context->shutdown();
    }
}
