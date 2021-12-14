<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\player\Player;

final class SessionManager {
    private static ?self $instance = null;

    public static function getInstance() : self {
        return self::$instance ?? (self::$instance = new self);
    }

    /** @var array<int, Session> */
    private $sessions = [];

    public function getSession(Player $player) : ?Session {
        return $this->sessions[$player->getId()] ?? null;
    }

    public function createSession(Player $player) : Session {
        $session = new Session($player);
        $this->sessions[$player->getId()] = $session;
        return $session;
    }

    public function removeSession(Player $player) : void {
        if(!isset($this->sessions[$player->getId()])) {
            return;
        }

        $session = $this->sessions[$player->getId()];
        unset($this->sessions[$player->getId()]);
        $session->close();
    }

    public function shutdown() : void {
        foreach($this->sessions as $session) {
            $session->close();
        }
        $this->sessions = [];
    }
}
