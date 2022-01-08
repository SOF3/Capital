<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

/**
 * Classes that can be instantiated from a Context.
 */
interface FromContext {
    /**
     * Instantiates the class from the context.
     */
    public static function instantiateFromContext(Context $context) : static;
}
