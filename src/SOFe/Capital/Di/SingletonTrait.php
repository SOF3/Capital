<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Closure;
use Generator;
use pocketmine\plugin\PluginException;
use ReflectionClass;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Plugin\MainClass;

use function is_subclass_of;
use function version_compare;

trait SingletonTrait {
    public static function get(Context $context) : Generator {
        $class = static::class;

        $self = self::getOrNull($context);

        if ($self === null) {
            if (!is_subclass_of($class, FromContext::class)) {
                throw new RuntimeException("$class must implement FromContext or be manually stored into Context");
            }

            $self = yield from $context->loadOrStoreAsync($class, $class::instantiateFromContext($context));
        }

        return $self;
    }

    public static function getOrNull(Context $context) : ?static {
        $class = static::class;

        if (!is_subclass_of($class, Singleton::class)) {
            throw new RuntimeException("$class must implement Singleton to use SingletonTrait");
        }

        return $context->fetchClass(static::class);
    }

    /**
     * @param Closure(self): (Generator<mixed, mixed, mixed, void>|null) $then
     */
    public static function api(string $minimumApi, Closure $then) : void {
        $class = static::class;
        $modClass = (new ReflectionClass($class))->getNamespaceName() . "\\Mod";
        $version = $modClass::API_VERSION;

        if (version_compare($minimumApi, $version, ">")) {
            throw new PluginException("Plugin requires Capital $minimumApi but current version is $version");
        }

        Await::f2c(function() use ($then) {
            $self = yield from self::get(MainClass::$context);
            $ret = $then($self);
            if ($ret instanceof Generator) {
                yield from $ret;
            }
        });
    }

    public function close() : void {
    }
}
