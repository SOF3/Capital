<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use function min;

/**
 * @template P
 */
final class CachedSingleQuery {
    /**
     * @param SingleQuery<P> $query
     */
    public function __construct(
        public SingleQuery $query,
        public int $updateFrequencyTicks,
    ) {
    }

    public function requestUpdateFrequencyTicks(int $updateFrequencyTicks) : void {
        $this->updateFrequencyTicks = min($this->updateFrequencyTicks, $updateFrequencyTicks);
    }
}
