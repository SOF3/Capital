<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Ramsey\Uuid\UuidInterface;

final class TransactionRef {
    public function __construct(
        private UuidInterface $id,
    ) {}

    public function getId() : UuidInterface {
        return $this->id;
    }
}
