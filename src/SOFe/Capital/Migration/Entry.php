<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

final class Entry {
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        public int $balance,
        public array $labels,
    ) {
    }
}
