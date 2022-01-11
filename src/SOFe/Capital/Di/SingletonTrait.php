<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Generator;
use RuntimeException;
use function is_subclass_of;

trait SingletonTrait {
    public static function get(Context $context) : Generator {
        $class = static::class;

        $self = self::getOrNull($context);

        if($self === null) {
            if(!is_subclass_of($class, FromContext::class)) {
                throw new RuntimeException("$class must implement FromContext or be manually stored into Context");
            }

            $self = yield from $context->loadOrStoreAsync($class, $class::instantiateFromContext($context));
        }

        return $self;
    }

    public static function getOrNull(Context $context) : ?static {
        $class = static::class;

        if(!is_subclass_of($class, Singleton::class)) {
            throw new RuntimeException("$class must implement Singleton to use SingletonTrait");
        }

        return $context->fetchClass(static::class);
    }

    public function close() : void {}
}
