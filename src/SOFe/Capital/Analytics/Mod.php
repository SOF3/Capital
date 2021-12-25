<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use pocketmine\Server;
use SOFe\Capital\Config\Config;
use SOFe\Capital\Database\Database;
use SOFe\Capital\MainClass;
use SOFe\Capital\TypeMap\ModInterface;
use SOFe\Capital\TypeMap\TypeMap;
use SOFe\InfoAPI\CommonInfo;
use SOFe\InfoAPI\InfoAPI;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(TypeMap $typeMap) : Generator {
        false && yield;

        InfoAPI::provideFallback(DynamicInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));
        InfoAPI::provideFallback(CommandArgsInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));

        $config = Config::get($typeMap);
        $plugin = MainClass::get($typeMap);
        $db = Database::get($typeMap);

        foreach($config->analytics->singleCommands as $command) {
            $command->register($plugin);
        }

        foreach($config->analytics->topCommands as $command) {
            $command->register($plugin, $db);
        }
    }

    public static function shutdown(TypeMap $typeMap) : void {}
}
