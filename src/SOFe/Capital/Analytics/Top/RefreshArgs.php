<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Top;

use SOFe\Capital\Config\Parser;

final class RefreshArgs {
    /**
     * @param int $batchSize Number of cache entries to recompute per batch.
     * @param int $batchFrequency Number of seconds between batches.
     * @param int $expiry Number of seconds before a cache entry is flagged to be recomputed.
     */
    public function __construct(
        public int $batchSize,
        public int $batchFrequency,
        public int $expiry,
    ) {
    }

    public static function parse(Parser $config) : self {
        return new RefreshArgs(
            batchSize: $config->expectInt("batch-size", 200, <<<'EOT'
                Number of players to recompute at once.
                Data are only recomputed if they are older than recompute-frequency.
                Try reducing this value if the database server is having lag spikes.
                EOT),
            batchFrequency: $config->expectInt("batch-frequency", 10, <<<'EOT'
                The number of seconds between batches.

                If there are multiple servers connected to the same database,
                the batch frequency is scheduled independently on each server,
                but the CPU and memory consumed are still on the database server.
                You may want to multiply this frequency by the number of servers.
                EOT),
            expiry: $config->expectInt("recompute-frequency", 86400, <<<'EOT'
                The number of seconds for which metrics are considered valid and will not be recomputed.

                Originally, the metrics of a player are recomputed every (batch-frequency * number-of-active-accounts / batch-size) seconds.
                This wastes CPU and memory on the database server if there are too few active accounts.
                Setting a higher recompute-frequency reduces the frequency of recomputation and saves electricity,
                but the top list results may be more outdated.
                Note that recompute-frequency does nothing if it is lower than (batch-frequency * number-of-active-accounts / batch-size).
                EOT),
        );
    }
}
