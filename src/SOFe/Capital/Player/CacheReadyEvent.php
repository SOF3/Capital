<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\event\Event;
use pocketmine\player\Player;

/**
 * Called when a player's displayable account cache is ready.
 *
 * Capital only fetches accounts labelled for InfoAPI display.
 */
final class CacheReadyEvent extends Event {
    public function __construct(
        private Player $player,
    ) {}

    public function getPlayer() : Player {
        return $this->player;
    }
}
