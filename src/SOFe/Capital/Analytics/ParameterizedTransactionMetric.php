<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Closure;
use Generator;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\TransactionQueryMetric;

/**
 * @template P
 * @implements Query<P>
 */
final class TransactionQuery implements Query {
    /**
     * @param Closure(P): LabelSelector $labelSelector
     */
    public function __construct(
        public TransactionQueryMetric $metric,
        public Closure $labelSelector,
    ) {
    }

    /**
     * @return Generator<mixed, mixed, mixed, int|float>
     */
    public function fetch($p, Database $db) : Generator {
        $selector = ($this->labelSelector)($p);
        [$result] = yield from $db->aggregateTransactions($selector, [$this->metric]);
        return $result;
    }
}
