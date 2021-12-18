<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use SOFe\Capital\Database\Database;

/**
 * @template K
 * @template V
 */
interface CacheType {
    /**
     * A function to obtain a unique bytestring from the key, uesd for array indexing.
     * @param K $key
     */
    public function keyToString($key) : string;

    /**
     * An async function to fetch the value for a isingle key.
     * @param K $key
     * @return Generator<mixed, mixed, mixed, V>
     */
    public function fetchEntry(Database $db, $key) : Generator;

    /**
     * An async function to fetch values for multiple keys.
     *
     * @param list<string> $keys
     * @return Generator<mixed, mixed, mixed, array<string, V>>
     */
    public function fetchEntries(Database $db, array $keys) : Generator;

    /**
     * A function called when an entry is refreshed.
     *
     * $old and $new may be identical.
     *
     * Called after the cache is updated.
     *
     * @param string $key the keyToString value of the key.
     * @param V $old
     * @param V $new
     * @return ?VoidPromise
     */
    public function onEntryRefresh(string $key, $old, $new) : ?Generator;


    /**
     * An async function called when all references to an entry are freed.
     *
     * Called after the entry is removed from the cache.
     *
     * @param string $key the keyToString value of the key of the entry being freed.
     * @param V $value the last value of the entry.
     * @return ?VoidPromise
     */
    public function onEntryFree(string $key, $value) : ?Generator;
}
