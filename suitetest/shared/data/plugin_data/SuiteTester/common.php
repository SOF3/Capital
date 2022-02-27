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
            $bobMessage = 'You have received $10. You now have $110 left.';
            $bobPromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $bobMessage), false, EventPriority::MONITOR, false);

            yield from Await::all([$alicePromise, $bobPromise]);
        },

        "take money" => function() use($server) {
            false && yield;
            $alice = $server->getPlayerExact("alice");
            $alice->chat("/takemoney alice 15");
        },
        "wait money deduct message" => function() use($server, $std) {
            $alice = $server->getPlayerExact("alice");
            $ackMessage = 'You have taken $15 from Alice. They now have $85 left.';
            $ackPromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $alice && str_contains($event->message, $ackMessage), false, EventPriority::MONITOR, false);

            $deductMessage = 'An admin took $15 from you. You now have $85 left.';
            $deductPromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $alice && str_contains($event->message, $deductMessage), false, EventPriority::MONITOR, false);

            yield from Await::all([$ackPromise, $deductPromise]);
        },

        "pay money" => function() use($server) {
            false && yield;
            $alice = $server->getPlayerExact("alice");
            $alice->chat("/pay bob 3");
        },
        "wait pay message" => function() use($server, $std) {
            $alice = $server->getPlayerExact("alice");
            $aliceMessage = 'You have sent $3 to Bob. You now have $82 left.';
            $alicePromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $alice && str_contains($event->message, $aliceMessage), false, EventPriority::MONITOR, false);

            $bob = $server->getPlayerExact("bob");
            $bobMessage = 'You have received $3 from Alice. You now have $113 left.';
            $bobPromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $bobMessage), false, EventPriority::MONITOR, false);

            yield from Await::all([$alicePromise, $bobPromise]);
        },

        "bob check money" => function() use($server, $std, $plugin) {
            yield from $std->sleep(10); // to wait for refresh

            $bob = $server->getPlayerExact("bob");
            $plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $bob->chat("/checkmoney")));

            $message = 'Bob has $113.';
            yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && str_contains($event->message, $message), false, EventPriority::MONITOR, false);
        },

        "alice check bob money" => function() use($server, $std, $plugin) {
            $alice = $server->getPlayerExact("alice");
            $plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $alice->chat("/checkmoney bob")));

            $message = 'Bob has $113.';
            yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $alice && str_contains($event->message, $message), false, EventPriority::MONITOR, false);
        },

        "bob check top money" => function() use($server, $std, $plugin) {
            yield from $std->sleep(200); // to wait for batch

            $bob = $server->getPlayerExact("bob");
            $plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $bob->chat("/richest")));

            foreach([
                'Showing page 1 of 1',
                '#1 bob: $113',
                '#2 alice: $82',
            ] as $message) {
                yield from $std->awaitEvent(PlayerReceiveMessageEvent::class,
                    fn($event) => $event->player === $bob && str_contains($event->message, $message), false, EventPriority::MONITOR, false);
            }
        },
    ];
};
