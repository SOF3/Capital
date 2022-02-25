<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\InfoAPI\NumberInfo;

final class CachedSingleValue {
    public function __construct(public ?float $value) {
    }

    public function asInfo() : ?NumberInfo {
        return $this->value !== null ? new NumberInfo($this->value) : null;
    }
}
