<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\event\Event;
use pocketmine\player\Player;

/**
 * An event dispatched when a player's accounts are ready.
 *
 * This means the player either has the existing accounts with overwriteLabels updated,
 * and/or has the new default accounts created,
 * and/or has migration accounts migrated.
 */
final class SessionReadyEvent extends Event {
    public function __construct(
        private Player $player,
        private int $createdCount,
        private int $migratedCount,
        private int $matchingCount,
    ) {}

    public function getPlayer() : Player {
        return $this->player;
    }

    /**
     * Returns the number of specs requiring account creation.
     */
    public function getCreatedCount() : int {
        return $this->createdCount;
    }


    /**
     * Returns the number of specs requiring account migation.
     */
    public function getMigratedCount() : int {
        return $this->migratedCount;
    }

    /**
     * Returns the number of specs matching existing accounts.
     */
    public function getMatchingCount() : int {
        return $this->matchingCount;
    }
}
