<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

/**
 * An initial account for a player.
 */
final class InitialAccount {
    /**
     * @param int $initialValue The default money. Only new accounts are affected.
     * @param array<string, string> $selectorLabels Labels used to identify an account.
     * @param array<string, string> $initialLabels Default non-identifying labels for new accounts.
     * @param array<string, string> $overwriteLabels Labels forced on the account. If they are missing or changed, they will be changed back. Used for modifying default configuration.
     */
    public function __construct(
        public int $initialValue,
        public array $selectorLabels,
        public array $initialLabels,
        public array $overwriteLabels,
    ) {}
}
