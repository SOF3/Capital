<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

/**
 * @template V
 */
final class CacheEntry {
    private int $refCount = 1;

    /**
     * @param V $value
     */
    public function __construct(
        private $value,
    ) {}

    public function incRefCount() : void {
        $this->refCount++;
    }

    public function decRefCount() : void {
        $this->refCount--;
    }

    public function getRefCount() : int {
        return $this->refCount;
    }

    /**
     * @return V
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * @param V $value
     */
    public function setCachedValue($value) : void {
        $this->value = $value;
    }
}
