<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use pocketmine\event\Event;

/**
 * An event dispatched when a player's accounts are ready.
 *
 * This means the player either has the existing accounts with overwriteLabels updated,
 * and/or has the new default accounts created,
 * and/or has migration accounts migrated.
 */
final class ConfigPopulateEvent extends Event {
    public function __construct(
        private Config $config,
    ) {}

    public function getConfig() : Config {
        return $this->config;
    }
}
