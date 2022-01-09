<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use pocketmine\Server;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\ModInterface;
use SOFe\Capital\MainClass;
use SOFe\InfoAPI\CommonInfo;
use SOFe\InfoAPI\InfoAPI;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(Context $context) : Generator {
        false && yield;

        InfoAPI::provideFallback(DynamicInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));
        InfoAPI::provideFallback(CommandArgsInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));

        $config = Config::get($context);
        $plugin = MainClass::get($context);
        $db = Database::get($context);

        foreach($config->singleCommands as $command) {
            $command->register($plugin);
        }

        foreach($config->topCommands as $command) {
            $command->register($plugin, $db);
        }
    }

    public static function shutdown(Context $context) : void {}
}
