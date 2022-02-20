<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\Capital\Config\Parser;

final class TopPaginationArgs {
    /**
     * @param int $perPage The number of results to display per page.
     * @param int $limit The maximum number of results to display.
     */
    public function __construct(
        public int $perPage,
        public int $limit,
    ) {
    }

    public static function parse(Parser $config) : self {
        $perPage = $config->expectInt("per-page", 5, "Number of top players to display per page");
        $limit = $config->expectInt("limit", 5, "Total number of top players to display through this command.");
        return new self($perPage, $limit);
    }
}
