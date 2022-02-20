<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\Capital\Config\Parser;

final class TopMessages {
    public function __construct(
        public string $header,
        public string $entry,
        public string $footer,
    ) {
    }

    public static function parse(Parser $config) : self {
        return new self(
            header: $config->expectString("header", "Showing page {page} of {totalPages}", "The top line of the page."),
            entry: $config->expectString("entry", "#{rank} {name}: {value}", "This line is repeated for each entry in the page."),
            footer: $config->expectString("footer", "", "The bottom line of the page."),
        );
    }
}
