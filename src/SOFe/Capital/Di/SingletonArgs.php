<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * A trait that implements [`FromContext`] by
 * passing singleton arguments into the constructor or the `fromSingletonArgs` static factory.
 */
trait SingletonArgs {
    /**
     * Returns the instance of this class in the context.
     */
    public static function instantiateFromContext(Context $context) : static {
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

        $args = $context->resolveArgs($func);

        return $constructor($args);
    }
}
