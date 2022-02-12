<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Ramsey\Uuid\UuidInterface;

/**
 * An immutable cached version of an account.
 *
 * Do not persist this object. The fields will not be updated even if the cache is refreshed.
 */
final class CachedAccount {
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        private UuidInterface $uuid,
        private int $value,
        private array $labels,
    ) {
    }

    public function getUuid() : UuidInterface {
        return $this->uuid;
    }

    public function getValue() : int {
        return $this->value;
    }

    /**
     * @return array<string, string>
     */
    public function getLabels() : array {
        return $this->labels;
    }
}
