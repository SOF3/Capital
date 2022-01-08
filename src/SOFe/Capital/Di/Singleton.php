<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

/**
 * A marker interface for classes that only have one instance in each Context.
 */
interface Singleton {
    /**
     * Returns the instance of this class in the context.
     */
    public static function get(Context $context) : static;
}
