<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Single;

use function min;

/**
 * The configuration for a SingleQuery whose result is cached locally and refreshd periodically.
 * @template P
 */
final class Cached {
    /**
     * @param Query<P> $query
     */
    public function __construct(
        public Query $query,
        public int $updateFrequencyTicks,
    ) {
    }

    public function requestUpdateFrequencyTicks(int $updateFrequencyTicks) : void {
        $this->updateFrequencyTicks = min($this->updateFrequencyTicks, $updateFrequencyTicks);
    }
}
