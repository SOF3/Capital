<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;

final class EventListener implements Listener {
    public function onPlayerLogin(PlayerLoginEvent $event) : void {
        $player = $event->getPlayer();
        SessionManager::getInstance()->createSession($player);
    }

    public function onPlayerQuit(PlayerQuitEvent $event) : void {
        $player = $event->getPlayer();
        SessionManager::getInstance()->removeSession($player);
    }
}
