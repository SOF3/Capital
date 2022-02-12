<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use SOFe\Capital\Database\Database;

/**
 * @template P
 */
interface Query {
    /**
     * @param P $p
     * @return Generator<mixed, mixed, mixed, int|float>
     */
    public function fetch($p, Database $db) : Generator;
}
