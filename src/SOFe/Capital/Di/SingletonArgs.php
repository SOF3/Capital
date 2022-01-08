<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use ReflectionClass;
use ReflectionException;

trait SingletonArgs {
    /**
     * Returns the instance of this class in the context.
     */
    public static function instantiateFromContext(Context $context) : static {
        $reflect = new ReflectionClass(static::class);

        try {
            $func = $reflect->getMethod("fromSingletonArgs");
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
