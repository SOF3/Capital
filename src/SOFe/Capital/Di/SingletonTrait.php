<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use RuntimeException;
use function is_subclass_of;

trait SingletonTrait {
    /**
     * Returns the instance of this class in the context.
     */
    public static function get(Context $context) : static {
        $class = static::class;

        if(!is_subclass_of($class, Singleton::class)) {
            throw new RuntimeException("$class must implement Singleton to use SingletonTrait");
        }

        $self = $context->fetchClass(static::class);

        if($self === null) {
            if(!is_subclass_of($class, FromContext::class)) {
                throw new RuntimeException("$class must implement FromContext or be manually stored into Context");
            }

            $self = $class::instantiateFromContext($context);
            $context->store($self);
        }

        return $self;
    }
}
