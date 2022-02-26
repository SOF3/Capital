<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Single;

use Closure;
use Generator;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;

/**
 * @template P
 * @implements Query<P>
 */
final class AccountQuery implements Query {
    /**
     * @param Closure(P): LabelSelector $labelSelector
     */
    public function __construct(
        public AccountQueryMetric $metric,
        public Closure $labelSelector,
    ) {
    }

    /**
     * @return Generator<mixed, mixed, mixed, int|float>
     */
    public function fetch($p, Database $db) : Generator {
        $selector = ($this->labelSelector)($p);
        [$result] = yield from $db->aggregateAccounts($selector, [$this->metric]);
        return $result;
    }
}
