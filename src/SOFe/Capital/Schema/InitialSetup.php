<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\ParameterizedLabelSet;
use SOFe\InfoAPI\Info;

/**
 * An initial account for a player.
 *
 * @template I of Info
 */
final class InitialSetup {
    /**
     * @param ParameterizedLabelSet<I> $initialLabels Default non-identifying labels for new accounts. Not applied on migration accounts.
     * @param int $initialValue The default money. Only new accounts are affected.
     */
    public function __construct(
        public ParameterizedLabelSet $initialLabels,
        public int $initialValue,
    ) {}
}
