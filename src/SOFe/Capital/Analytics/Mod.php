<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use pocketmine\Server;
use SOFe\Capital\Capital;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use SOFe\InfoAPI\CommonInfo;
use SOFe\InfoAPI\InfoAPI;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(Config $config, MainClass $plugin, Capital $api, Database $db) : self {
        InfoAPI::provideFallback(DynamicInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));
        InfoAPI::provideFallback(CommandArgsInfo::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));

        foreach($config->singleCommands as $command) {
            $command->register($plugin, $api);
        }

        foreach($config->topCommands as $command) {
            $command->register($plugin, $db);
        }

        return new self;
    }
}
