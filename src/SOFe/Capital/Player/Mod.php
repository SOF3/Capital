<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use Generator;
use pocketmine\Server;
use SOFe\Capital\Config\Config;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\ModInterface;
use SOFe\Capital\MainClass;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(Context $context) : Generator {
        false && yield;

        $context->call(function(MainClass $plugin, Config $config, SessionManager $sessionManager) use($context) {
            $listener = EventListener::instantiateFromContext($context);
            Server::getInstance()->getPluginManager()->registerEvents($listener, $plugin);

            foreach($config->player->infoNames as $name) {
                InfoAPI::provideInfo(
                    PlayerInfo::class, NumberInfo::class,
                    "capital.player.$name",
                    function(PlayerInfo $info) use($name, $sessionManager) : ?NumberInfo {
                        $session = $sessionManager->getSession($info->getValue());
                        $value = $session?->getInfo($name);

                        if($value !== null) {
                            return new NumberInfo((float) $value);
                        }
                        return null;
                    }
                );
            }
        });
    }

    public static function shutdown(Context $context) : void {
        SessionManager::get($context)->shutdown();
    }
}
