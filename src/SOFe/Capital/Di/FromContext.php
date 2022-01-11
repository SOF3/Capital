<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Generator;

/**
 * Classes that can be instantiated from a Context.
 */
interface FromContext {
    /**
     * Instantiates the class from the context.
     *
     * @return Generator<mixed, mixed, mixed, static>
     */
    public static function instantiateFromContext(Context $context) : Generator;
}
