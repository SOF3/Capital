<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\InfoAPI\PlayerInfo;

/**
 * An initial account for a player.
 */
final class InitialAccount {
    /**
     * @param int $initialValue The default money. Only new accounts are affected.
     * @param ParameterizedLabelSelector<PlayerInfo> $selectorLabels Labels used to identify an account.
     * @param ParameterizedLabelSelector<PlayerInfo> $migrationLabels Labels used to search for fallback accounts for migration purposes.
     * @param ParameterizedLabelSelector<PlayerInfo> $initialLabels Default non-identifying labels for new accounts. Not applied on migration accounts.
     * @param ParameterizedLabelSelector<PlayerInfo> $overwriteLabels Labels forced on the account. If they are missing or changed, they will be changed back. Used for modifying default configuration.
     */
    public function __construct(
        public int $initialValue,
        public ParameterizedLabelSelector $selectorLabels,
        public ParameterizedLabelSelector $migrationLabels,
        public ParameterizedLabelSelector $initialLabels,
        public ParameterizedLabelSelector $overwriteLabels,
    ) {}
}
