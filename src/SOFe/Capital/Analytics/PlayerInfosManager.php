<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

final class PlayerInfosManager {
    /** @var array<int, CachedSingleValue> */
    private array $cache = [];

    /**
     * @param CachedSingleQuery<Player> $query
     */
    public function __construct(
        private string $name,
        private CachedSingleQuery $query,
    ) {
    }

    public function register(Plugin $plugin, AwaitStd $std, Database $db) : void {
        Server::getInstance()->getPluginManager()->registerEvent(
            event: PlayerLoginEvent::class,
            handler: fn(PlayerLoginEvent $event) => $this->startSession($event->getPlayer(), $std, $db),
            priority: EventPriority::MONITOR,
            plugin: $plugin,
            handleCancelled: false,
        );

        InfoAPI::provideInfo(PlayerInfo::class, NumberInfo::class, "capital.analytics.single.{$this->name}",
            function(PlayerInfo $info) : ?NumberInfo {
                if (isset($this->cache[$info->getValue()->getId()])) {
                    return $this->cache[$info->getValue()->getId()]->asInfo();
                }

                return null;
            });
    }

    private function startSession(Player $player, AwaitStd $std, Database $db) : void {
        $this->cache[$player->getId()] = new CachedSingleValue(null);

        Await::g2c($this->sessionLoop($player, $std, $db));
    }

    /**
     * @return VoidPromise
     */
    private function sessionLoop(Player $player, AwaitStd $std, Database $db) : Generator {
        while ($player->isOnline()) {
            $value = yield from $this->query->query->fetch($player, $db);
            $this->cache[$player->getId()]->value = $value;

            yield from $std->sleep($this->query->updateFrequencyTicks);
        }

        unset($this->cache[$player->getId()]);
    }
}
