<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Closure;
use SOFe\Capital\LabelSet;

/**
 * An initial account for a player.
 */
final class InitialSetup {
    /**
     * @param LabelSet $initialLabels Default non-identifying labels for new accounts. Not applied on migration accounts.
     * @param int $initialValue The default money. Only new accounts are affected.
     */
    public function __construct(
        public LabelSet $initialLabels,
        public int $initialValue,
    ) {}

    public function andInitialLabel(LabelSet $labels) : self {
        return new self(
            initialLabels: $this->initialLabels->and($labels),
            initialValue: $this->initialValue,
        );
    }
}
