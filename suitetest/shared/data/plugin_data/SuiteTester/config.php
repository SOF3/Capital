<?php

use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\Server;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\player\Player;
use SOFe\Capital\Player\CacheReadyEvent;
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
    $server = Server::getInstance();

    return [
        "wait for players to join" => function() use($std) {
            yield from Await::all([
                $std->awaitEvent(CacheReadyEvent::class, fn($event) => $event->getPlayer()->getName() === "Alice", false, EventPriority::MONITOR, false),
                $std->awaitEvent(CacheReadyEvent::class, fn($event) => $event->getPlayer()->getName() === "Bob", false, EventPriority::MONITOR, false),
            ]);
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
            $aliceMessage =  "You received 10 coins from Alice";
            $alicePromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $alice && $event->message === $aliceMessage, false, EventPriority::MONITOR, false);

            $bob = $server->getPlayerExact("bob");
            $bobMessage =  "You received 10 coins from Alice";
            $bobPromise = $std->awaitEvent(PlayerReceiveMessageEvent::class,
                fn($event) => $event->player === $bob && $event->message === $bobMessage, false, EventPriority::MONITOR, false);

            yield from Await::all([$alicePromise, $bobPromise]);
        },
    ];
};
