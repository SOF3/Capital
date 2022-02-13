<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use SOFe\Capital\Config\DynamicCommand;

final class ConfigTop {
    public function __construct(
        public DynamicCommand $command,
        public int $listLength,
        public TopQueryArgs $queryArgs,
        public TopRefreshArgs $refreshArgs,
    ) {
    }
}
