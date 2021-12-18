<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\player\Player;
use SOFe\Capital\Cache\Cache;
use SOFe\Capital\Config\Config;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Singleton;
use SOFe\Capital\SingletonTrait;

final class SessionManager implements Singleton {
    use SingletonTrait;

    /** @var array<int, Session> */
    private $sessions = [];

    public function __construct(
        private Cache $cache,
        private Config $config,
        private Database $database,
    ) {}

    public function getSession(Player $player) : ?Session {
        return $this->sessions[$player->getId()] ?? null;
    }

    public function createSession(Player $player) : Session {
        $session = new Session($this->cache, $this->config, $this->database, $player);
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
