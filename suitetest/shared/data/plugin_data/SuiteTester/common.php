<?php

use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\Server;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use SOFe\Capital\Di\StoreEvent;
use SOFe\Capital\Loader\Loader;
use SOFe\SuiteTester\Await;
use SOFe\SuiteTester\Main;

class PlayerReceiveMessageEvent extends Event {
    public function __construct(
        public Player $player,
        public string $message,
        public int $type,
    ) {}
}

return function() {
    $std = Main::$std;
    $plugin = Main::getInstance();
    $server = Server::getInstance();

    return [
        "wait for Capital to initialize" => function() use($std) {
            yield from $std->awaitEvent(StoreEvent::class, fn($event) => $event->getObject() instanceof Loader, false, EventPriority::MONITOR, false);
        },
        "wait for two players to join" => function() use($server, $std) {
            yield from $std->awaitEvent(PlayerJoinEvent::class, fn($_) => count($server->getOnlinePlayers()) === 2, false, EventPriority::MONITOR, false);
        },
        "setup chat listeners" => function() use($server) {
            false && yield;
            foreach($server->getOnlinePlayers() as $player) {
                $player->getNetworkSession()->registerPacketListener(new ClosureFakePlayerPacketListener(
                    function(ClientboundPacket $packet, NetworkSession $session) use($player, $server) : void {
                        if($packet instanceof TextPacket) {
                            $event = new PlayerReceiveMessageEvent($player, $packet->message, $packet->type);
                            $event->call();
                            $server->getLogger()->debug("{$player->getName()} received message: $packet->message");
                        }
                    }
                ));
            }
        },
        "send money" => function() use($server) {
            false && yield;
            $alice = $server->getPlayerExact("alice");
            $alice->chat("/addmoney bob 10");
        },
        "wait money receive message" => function() use($server, $std) {
            $alice = $server->getPlayerExact("alice");
            $aliceMessage = 'Bob has received $10. They now have $110 left.';
            $alicePromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $alice && str_contains($event->message, $aliceMessage), false, EventPriority::MONITOR, false);

            $bob = $server->getPlayerExact("bob");
            $bobMessage = TextFormat::GREEN . 'You have received $10. You now have $110 left.';
            $bobPromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $bobMessage), false, EventPriority::MONITOR, false);

            yield from Await::all([$alicePromise, $bobPromise]);
        },

        "bob check money" => function() use($server, $std, $plugin) {
            $bob = $server->getPlayerExact("bob");
            $plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $bob->chat("/mymoney")));

            $message = 'You have $110 in total.';
            yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $message), false, EventPriority::MONITOR, false);
        },

        "alice check bob money" => function() use($server, $std, $plugin) {
            $alice = $server->getPlayerExact("alice");
            $plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $alice->chat("/checkmoney bob")));

            $message = 'Bob has $110 in total.';
            yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $alice && str_contains($event->message, $message), false, EventPriority::MONITOR, false);
        },

        "bob check top money" => function() use($server, $std, $plugin) {
            $bob = $server->getPlayerExact("bob");
            $plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $bob->chat("/topmoney")));

            $message = 'Top 5 players:';
            yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $message), false, EventPriority::MONITOR, false);

            $message = '#1: Bob - $110';
            yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $message), false, EventPriority::MONITOR, false);

            $message = '#2: Alice - $100';
            yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $message), false, EventPriority::MONITOR, false);
        },
    ];
};
