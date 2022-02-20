<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use RuntimeException;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\NumberInfo;

final class PaginationInfo extends Info {
    public function __construct(
        public NumberInfo $page,
        public NumberInfo $totalPages,
        public NumberInfo $perPage,
        public NumberInfo $total,
        public NumberInfo $firstRank,
        public NumberInfo $lastRank,
    ) {
    }

    public function toString() : string {
        throw new RuntimeException("PaginationInfo must not be returned as a provided info");
    }
}
