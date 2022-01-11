<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Generator;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * A trait that implements [`FromContext`] by
 * passing singleton arguments into the constructor or the `fromSingletonArgs` static factory.
 */
trait SingletonArgs {
    public static function instantiateFromContext(Context $context) : Generator {
        $reflect = new ReflectionClass(static::class);

        try {
            $func = $reflect->getMethod("fromSingletonArgs");
            if(!$func->isStatic()) {
                throw new RuntimeException("fromSingletonArgs() must be static");
            }
            $constructor = fn($args) => $func->invokeArgs(null, $args);
        } catch(ReflectionException $_) {
            $func = $reflect->getConstructor();
            if($func === null) {
                return $reflect->newInstance();
            }

            $constructor = fn($args) => $reflect->newInstanceArgs($args);
        }

        $args = yield from $context->resolveArgs($func, static::class);

        $ret = $constructor($args);

        if($ret instanceof Generator) {
            $ret = yield from $ret;
        }

        return $ret;
    }
}
