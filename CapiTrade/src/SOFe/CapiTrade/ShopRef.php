<?php

declare(strict_types=1);

namespace SOFe\CapiTrade;

use Ramsey\Uuid\UuidInterface;

final class ShopRef {
    public function __construct(
        private UuidInterface $id,
    ) {
    }

    public function getId() : UuidInterface {
        return $this->id;
    }
}
