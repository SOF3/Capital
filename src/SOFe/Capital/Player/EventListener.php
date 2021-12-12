<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;

final class EventListener implements Listener {
    public function onPlayerLogin(PlayerLoginEvent $event) : void {
        $player = $event->getPlayer();
        SessionManager::getInstance()->createSession($player);
    }
}
