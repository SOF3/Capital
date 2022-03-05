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
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use SOFe\AwaitStd\AwaitStd;
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

class Context {
    /** @var AwaitStd $std do not type hint directly, because included files are not shaded */
    public $std;
    public Plugin $plugin;
    public Server $server;

    public function __construct() {
        $this->std = Main::$std;
        $this->plugin = Main::getInstance();
        $this->server = Server::getInstance();
    }

    public function awaitMessage(Player $who, string $messageSubstring, ...$args) : Generator {
        $expect = strtolower(sprintf($messageSubstring, ...$args));
        $this->server->getLogger()->debug("Waiting for message to {$who->getName()} " . json_encode($expect));
        return yield from $this->std->awaitEvent(
            event: PlayerReceiveMessageEvent::class,
            eventFilter: fn($event) => $event->player === $who && str_contains(strtolower($event->message), $expect),
            consume: false,
            priority: EventPriority::MONITOR,
            handleCancelled: false,
        );
    }
}

function init_steps(Context $context) : Generator {
    yield "wait for Capital to initialize" => function() use($context) {
        yield from $context->std->awaitEvent(StoreEvent::class, fn($event) => $event->getObject() instanceof Loader, false, EventPriority::MONITOR, false);
    };

    yield "wait for two players to join" => function() use($context) {
        $onlineCount = 0;
        foreach($context->server->getOnlinePlayers() as $player) {
            if($player->isOnline()) {
                $onlineCount += 1;
            }
        }
        if($onlineCount < 2) {
            yield from $context->std->awaitEvent(PlayerJoinEvent::class, fn($_) => count($context->server->getOnlinePlayers()) === 2, false, EventPriority::MONITOR, false);
        }

        yield from $context->std->sleep(10);
    };

    yield "setup chat listeners" => function() use($context) {
        false && yield;
        foreach($context->server->getOnlinePlayers() as $player) {
            $player->getNetworkSession()->registerPacketListener(new ClosureFakePlayerPacketListener(
                function(ClientboundPacket $packet, NetworkSession $session) use($player, $context) : void {
                    if($packet instanceof TextPacket) {
                        $context->server->getLogger()->debug("{$player->getName()} received message: $packet->message");

                        $event = new PlayerReceiveMessageEvent($player, $packet->message, $packet->type);
                        $event->call();
                    }
                }
            ));
        }
    };
}

function add_money_test(Context $context, string $adminName, string $targetName, int $amount, int $remain) : Generator {
    yield "send money" => function() use($context, $adminName, $targetName, $amount) {
        false && yield;
        $admin = $context->server->getPlayerExact($adminName);
        $admin->chat("/addmoney $targetName $amount");
    };
    yield "wait money receive message" => function() use($context, $adminName, $targetName, $amount, $remain) {
        $admin = $context->server->getPlayerExact($adminName);
        $target = $context->server->getPlayerExact($targetName);

        yield from Await::all([
            $context->awaitMessage($admin, '%s has received $%d. They now have $%d left.', $target->getName(), $amount, $remain),
            $context->awaitMessage($target, 'You have received $%d. You now have $%d left.', $amount, $remain),
        ]);
    };
}

function take_money_test(Context $context, string $adminName, string $targetName, int $amount, int $remain) : Generator {
    yield "take money" => function() use($context, $adminName, $targetName, $amount) {
        false && yield;
        $admin = $context->server->getPlayerExact($adminName);
        $admin->chat("/takemoney $targetName $amount");
    };
    yield "wait money deduct message" => function() use($context, $adminName, $targetName, $amount, $remain) {
        $admin = $context->server->getPlayerExact($adminName);
        $target = $context->server->getPlayerExact($targetName);

        yield from Await::all([
            $context->awaitMessage($admin, 'You have taken $%d from %s. They now have $%d left.', $amount, $target->getName(), $remain),
            $context->awaitMessage($target, 'An admin took $%d from you. You now have $%d left.', $amount, $remain),
        ]);
    };
}

function pay_money_test(Context $context, string $fromName, string $toName, int $amount, int $fromRemain, int $toRemain) : Generator {
    yield "pay money" => function() use($context, $fromName, $toName, $amount) {
        false && yield;
        $from = $context->server->getPlayerExact($fromName);
        $from->chat("/pay $toName $amount");
    };
    yield "wait pay message" => function() use($context, $fromName, $toName, $amount, $fromRemain, $toRemain) {
        $from = $context->server->getPlayerExact($fromName);
        $to = $context->server->getPlayerExact($toName);

        yield from Await::all([
            $context->awaitMessage($from, 'You have sent $%d to %s. You now have $%d left.', $amount, $to->getName(), $fromRemain),
            $context->awaitMessage($to, 'You have received $%d from %s. You now have $%d left.', $amount, $from->getName(), $toRemain),
        ]);
    };
}

function check_self_money(Context $context, string $playerName, int $expect) : Generator {
    yield "$playerName check money" => function() use($context, $playerName, $expect) {
        yield from $context->std->sleep(10); // to wait for refresh

        $player = $context->server->getPlayerExact($playerName);
        $context->plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $player->chat("/checkmoney")));

        yield from $context->awaitMessage($player, '%s has $%d.', $player->getName(), $expect);
    };
}

function check_other_money(Context $context, string $checkerName, string $checkedName, int $expect) : Generator {
    yield "$checkerName check $checkedName money" => function() use($context, $checkerName, $checkedName, $expect) {
        $checker = $context->server->getPlayerExact($checkerName);
        $checked = $context->server->getPlayerExact($checkedName);
        $context->plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $checker->chat("/checkmoney $checkedName")));

        yield from $context->awaitMessage($checker, '%s has $%d.', $checked->getName(), $expect);
    };
}

function check_top_money(Context $context, string $checkerName, array $messages) : Generator {
    yield "check top money" => function() use($context, $checkerName, $messages) {
        yield from $context->std->sleep(200); // to wait for batch

        $checker = $context->server->getPlayerExact($checkerName);
        $context->plugin->getScheduler()->scheduleTask(new ClosureTask(fn() => $checker->chat("/richest")));

        foreach($messages as $message) {
            yield from $context->awaitMessage($checker, '%s', $message);
        }
    };
}
