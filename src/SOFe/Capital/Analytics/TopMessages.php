<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

final class TopMessages {
    public function __construct(
        public string $header,
        public string $main,
    ) {}
}
