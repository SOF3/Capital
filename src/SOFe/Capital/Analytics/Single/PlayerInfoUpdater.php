<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Single;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Migration;
use SOFe\Capital\PostTransactionEvent;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

final class PlayerInfoUpdater {
    /** @var array<int, CachedValue> */
    private array $cache = [];

    /**
     * @param Cached<Player> $query
     */
    public function __construct(
        private string $name,
        private Cached $query,
    ) {
    }

    public function register(Plugin $plugin, AwaitStd $std, Database $db) : void {
        $this->registerListener($plugin, $std, $db);

        $this->registerInfo();
    }

    public function registerListener(Plugin $plugin, AwaitStd $std, Database $db) : void {
        $pm = Server::getInstance()->getPluginManager();

        $pm->registerEvent(
            event: PlayerLoginEvent::class,
            handler: fn(PlayerLoginEvent $event) => $this->startSession($event->getPlayer(), $std, $db),
            priority: EventPriority::MONITOR,
            plugin: $plugin,
            handleCancelled: false,
        );
        $pm->registerEvent(
            event: PlayerQuitEvent::class,
            handler: function(PlayerQuitEvent $event) {
                $id = $event->getPlayer()->getId();
                if (isset($this->cache[$id])) {
                    unset($this->cache[$id]);
                }
            },
            priority: EventPriority::MONITOR,
            plugin: $plugin,
            handleCancelled: false,
        );
        $pm->registerEvent(
            event: PostTransactionEvent::class,
            handler: function(PostTransactionEvent $event) {
                foreach ($event->getInvolvedPlayers() as $player) {
                    if (isset($this->cache[$player->getId()])) {
                        $value = $this->cache[$player->getId()];
                        $value->refreshNow();

                        $wg = $event->getRefreshWaitGroup();
                        $wg->add();
                        Await::f2c(function() use ($wg, $value) {
                            yield from $value->waitForRefresh();
                            $wg->done();
                        });
                    }
                }
            },
            priority: EventPriority::MONITOR,
            plugin: $plugin,
            handleCancelled: false,
        );
        $pm->registerEvent(
            event: Migration\CompleteEvent::class,
            handler: function(Migration\CompleteEvent $event) {
                $wg = $event->getRefreshWaitGroup();

                foreach ($this->cache as $value) {
                    $value->refreshNow();
                    $wg->add();

                    Await::f2c(function() use ($wg, $value) {
                        yield from $value->waitForRefresh();
                        $wg->done();
                    });
                }
            },
            priority: EventPriority::MONITOR,
            plugin: $plugin,
            handleCancelled: false,
        );
    }

    public function registerInfo() : void {
        InfoAPI::provideInfo(PlayerInfo::class, NumberInfo::class, "capital.analytics.single.{$this->name}",
            function(PlayerInfo $info) : ?NumberInfo {
                if (isset($this->cache[$info->getValue()->getId()])) {
                    return $this->cache[$info->getValue()->getId()]->asInfo();
                }

                return null;
            });
    }

    private function startSession(Player $player, AwaitStd $std, Database $db) : void {
        $value = new CachedValue(null);
        $this->cache[$player->getId()] = $value;

        Await::g2c($value->loop(fn() => $player->isOnline(), $std, $this->query, $player, $db));
    }
}
