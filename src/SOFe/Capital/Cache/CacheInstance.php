<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use function array_keys;
use function count;
use function json_encode;

/**
 * @template K
 * @template V
 */
final class CacheInstance {
    /** @var array<string, CacheEntry<V>> */
    private array $entries = [];

    /**
     * @param CacheType<K, V> $type
     */
    public function __construct(
        private Database $db,
        private AwaitStd $std,
        private CacheType $type,
    ) {}

    /**
     * Fetches the value for the given key if it is not already in the cache.
     * Increments the refcount for the entry by one.
     *
     * @param K $key
     * @return Generator<mixed, mixed, mixed, void>
     */
    public function fetch($key) : Generator {
        $string = $this->type->keyToString($key);

        if(isset($this->entries[$string])) {
            $this->entries[$string]->incRefCount();
            return;
        }

        $value = yield from $this->type->fetchEntry($this->db, $key);
        $entry = new CacheEntry($value);
        $this->entries[$string] = $entry;
    }

    /**
     * @param K $key
     * @return V
     */
    public function assertFetched($key) {
        $string = $this->type->keyToString($key);

        if(!isset($this->entries[$string])) {
            throw new RuntimeException("Key " . json_encode($string) . " was not fetched");
        }
        return $this->entries[$string]->getValue();
    }

    /**
     * @param K $key
     */
    public function free($key) : void {
        $string = $this->type->keyToString($key);

        if(!isset($this->entries[$string]) || $this->entries[$string]->getRefCount() === 0) {
            throw new RuntimeException("Attempt to free unreferenced cache entry $key");
        }

        $this->entries[$string]->decRefCount();
    }

    /**
     * @return VoidPromise
     */
    public function recycle() : Generator {
        $promises = [];

        foreach($this->entries as $key => $entry) {
            if($entry->getRefCount() === 0) {
                $promise = $this->type->onEntryFree($key, $this->entries[$key]->getValue());
                if($promise !== null) {
                    $promises[] = $promise;
                }
                unset($this->entries[$key]);
            }
        }

        if(count($promises) > 0) {
            yield from Await::all($promises);
        }
    }

    /**
     * @return VoidPromise
     */
    public function refresh() : Generator {
        $entries = yield from $this->type->fetchEntries($this->db, array_keys($this->entries));

        $promises = [];

        foreach($entries as $key => $value) {
            if(!isset($this->entries[$key])) {
                // recycled
                continue;
            }

            $original = $this->entries[$key]->getValue();
            $this->entries[$key]->setCachedValue($value);
            $promise = $this->type->onEntryRefresh($key, $original, $value);
            if($promise !== null) {
                $promises[] = $promise;
            }

            // for entries added during refresh, they should be new enough.
        }

        if(count($promises) > 0) {
            yield from Await::all($promises);
        }
    }

    /**
     * @return VoidPromise
     */
    public function refreshLoop(int $interval) : Generator {
        // This should run until the plugin disables, at which `$this->refresh()` will just never yield.
        while(true) {
            yield from $this->std->sleep($interval);
            yield from $this->refresh();
        }
    }
}
