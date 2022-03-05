<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use Generator;

/**
 * A migration data source.
 * Implementors must be safe to serialize() and unserialize().
 */
interface Source {
    /**
     * A generator that yields account entries.
     *
     * @return Generator<int, Entry, mixed, void>
     */
    public function generateEntries() : Generator;
}
