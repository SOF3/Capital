<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

final class AggregateTopEntry {
    /**
     * @param list<string> $groupValues
     * @param array<string, int|float> $metrics
     */
    public function __construct(
        private array $groupValues,
        private array $metrics,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getGroupValues() : array {
        return $this->groupValues;
    }

    /**
     * @return array<string, int|float>
     */
    public function getMetrics() : array {
        return $this->metrics;
    }
}
