<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

/**
 * Specifies the top list grouping policy.
 */
final class TopNSpec {
    /**
     * @param string $group The label uesd for grouping.
     * @param int $limit The number of top rows to fetch.
     * @param bool $descending Whether to sort in descending order.
     * @param string $order
     */
    public function __construct(
        public string $group,
        public int $limit,
        public bool $descending,
        public string $order,
    ) {}
}
