<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\Server;
use SOFe\Capital\MainClass;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

final class Mod {
    public static function init() : void {
        Server::getInstance()->getPluginManager()->registerEvents(new EventListener, MainClass::getInstance());

        foreach(MainClass::getInstance()->getConfig()->getNested("player.infos") as $name) {
            InfoAPI::provideInfo(
                PlayerInfo::class, NumberInfo::class,
                "capital.player.$name",
                function(PlayerInfo $info) use($name): ?NumberInfo {
                    $session = SessionManager::getInstance()->getSession($info->getValue());
                    $value = $session?->getInfo($name);

                    if($value !== null) {
                        return new NumberInfo((float) $value);
                    }
                    return null;
                }
            );
        }
    }
}
