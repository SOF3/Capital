<?php

declare(strict_types=1);

namespace SOFe\Capital;

/**
 * A marker interface to label classes that should be stored in TypeMap.
 *
 * Implies `SingletonArgs`.
 */
interface Singleton extends SingletonArgs {
}
