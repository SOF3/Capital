<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use Generator;
use pocketmine\Server;
use SOFe\Capital\Config\Config;
use SOFe\Capital\ModInterface;
use SOFe\Capital\MainClass;
use SOFe\Capital\TypeMap;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(TypeMap $typeMap) : Generator {
        false && yield;

        $plugin = MainClass::get($typeMap);
        $listener = EventListener::instantiate($typeMap);
        $config = Config::get($typeMap);
        $sessionManager = SessionManager::get($typeMap);

        Server::getInstance()->getPluginManager()->registerEvents($listener, $plugin);

        foreach($config->player->infoNames as $name) {
            InfoAPI::provideInfo(
                PlayerInfo::class, NumberInfo::class,
                "capital.player.$name",
                function(PlayerInfo $info) use($name, $sessionManager): ?NumberInfo {
                    $session = $sessionManager->getSession($info->getValue());
                    $value = $session?->getInfo($name);

                    if($value !== null) {
                        return new NumberInfo((float) $value);
                    }
                    return null;
                }
            );
        }
    }

    public static function shutdown(TypeMap $typeMap): void {
        SessionManager::get($typeMap)->shutdown();
    }
}
