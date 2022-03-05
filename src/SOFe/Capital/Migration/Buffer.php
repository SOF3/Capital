<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

final class Buffer {
    /** @var list<Entry> */
    public array $data = [];
    public bool $complete = false;
}
