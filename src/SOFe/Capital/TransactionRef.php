<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\Capital\Database\Database;

final class TransactionRef {
    public function __construct(
        private UuidInterface $id,
    ) {}

    public function getId() : UuidInterface {
        return $this->id;
    }
}
