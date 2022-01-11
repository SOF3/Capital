<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use Generator;
use pocketmine\Server;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    /**
     * @return Generator<mixed, mixed, mixed, self>
     */
    public static function fromSingletonArgs(Context $context, MainClass $plugin, Config $config, SessionManager $sessionManager) : Generator {
        $listener = yield from EventListener::instantiateFromContext($context);
        Server::getInstance()->getPluginManager()->registerEvents($listener, $plugin);

        foreach($config->infoNames as $name) {
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

        return new self;
    }
}
