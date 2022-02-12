<?php

declare(strict_types=1);

namespace SOFe\Capital;

use function implode;

final class LabelSet {
    /**
     * @param array<string, string> $entries
     */
    public function __construct(
        private array $entries,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getEntries() : array {
        return $this->entries;
    }

    public function and(LabelSet $other) : LabelSet {
        return new self($this->entries + $other->entries);
    }

    public function debugDisplay() : string {
        $output = [];
        foreach ($this->entries as $key => $value) {
            $output[] = $key . "=" . $value;
        }
        return implode(", ", $output);
    }
}
