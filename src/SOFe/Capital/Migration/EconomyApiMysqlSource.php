<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

final class EconomyApiMysqlSource implements Source {
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $db,
    ) {
    }
}
