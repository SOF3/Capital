<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use pocketmine\Server;
use SOFe\Capital\Config\Config;
use SOFe\Capital\MainClass;
use SOFe\Capital\ModInterface;
use SOFe\Capital\TypeMap;
use SOFe\InfoAPI\CommonInfo;
use SOFe\InfoAPI\InfoAPI;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(TypeMap $typeMap) : Generator {
        false && yield;

        InfoAPI::provideFallback(AnalyticsDynamicInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));
        InfoAPI::provideFallback(AnalyticsCommandArgsInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));

        $config = Config::get($typeMap);
        $plugin = MainClass::get($typeMap);

        foreach($config->analytics->commands as $command) {
            $command->register($plugin);
        }
    }

    public static function shutdown(TypeMap $typeMap) : void {}
}
