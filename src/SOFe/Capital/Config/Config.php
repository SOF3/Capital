<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use SOFe\Capital\Analytics;
use SOFe\Capital\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\MainClass;
use SOFe\Capital\Player;
use SOFe\Capital\Transfer;
use function yaml_parse_file;

final class Config implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /**
     * @param Database\Config $database settings for database connection.
     * @param Player\Config $player Settings related to players as account owners.
     * @param Transfer\Config $transfer Settings for the transfer module.
     * @param Analytics\Config $analytics Settings for the analytics module.
     */
    public function __construct(
        public Database\Config $database,
        public Player\Config $player,
        public Transfer\Config $transfer,
        public Analytics\Config $analytics,
    ) {}

    public static function fromSingletonArgs(MainClass $main) : self {
        $main->saveResource("config.yml");
        $main->saveResource("db.yml");

        $dbConfig = yaml_parse_file($main->getDataFolder() . "db.yml");
        $mainConfig = yaml_parse_file($main->getDataFolder() . "config.yml");

        return new self(
            database: Database\Config::load($dbConfig),
            player: Player\Config::default(),
            transfer: Transfer\Config::default(),
            analytics: Analytics\Config::default(),
        );
    }
}
