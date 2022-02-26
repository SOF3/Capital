<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Top;

use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\StringInfo;
use function array_map;

final class ResultEntry {
    /**
     * @param array<string, string> $displays
     */
    public function __construct(
        private int $rank,
        private float $value,
        private array $displays,
    ) {
    }

    public function asInfo(int $baseRank) : ResultEntryInfo {
        return new ResultEntryInfo(
            rank: new NumberInfo($baseRank + $this->rank),
            value: new NumberInfo($this->value),
            displays: array_map(fn(string $display) => new StringInfo($display), $this->displays),
        );
    }
}
