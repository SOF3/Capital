<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Generator;

/**
 * A marker interface for classes that only have one instance in each Context.
 */
interface Singleton {
    /**
     * Returns the instance of this class in the context.
     *
     * @return Generator<mixed, mixed, mixed, static>
     */
    public static function get(Context $context) : Generator;

    /**
     * Returns the instance of this class in the context
     * if it has been initialized.
     */
    public static function getOrNull(Context $context) : ?static;

    /**
     * Clean up the object during normal shutdown.
     */
    public function close() : void;
}
