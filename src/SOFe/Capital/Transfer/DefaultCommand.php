<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\ParameterizedLabelSet;

class DefaultCommand {
    public function __construct(
        public string $command,
        public string $permission,
        public bool $defaultOpOnly,
        public string $src,
        public string $dest,
        public float $rate,
        public int $minimumAmount,
        public int $maximumAmount,
        public ParameterizedLabelSet $transactionLabels,
        public Messages $messages,
    ) {}
}
