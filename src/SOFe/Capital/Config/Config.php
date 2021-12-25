<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use SOFe\Capital\Analytics;
use SOFe\Capital\Database;
use SOFe\Capital\Player;
use SOFe\Capital\TypeMap\Singleton;
use SOFe\Capital\TypeMap\SingletonTrait;
use SOFe\Capital\Transfer;
use SOFe\Capital\TypeMap\TypeMap;

final class Config implements Singleton {
    use SingletonTrait;

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

    public static function default(TypeMap $typeMap) : self {
        return new self(
            database: Database\Config::default($typeMap),
            player: Player\Config::default(),
            transfer: Transfer\Config::default(),
            analytics: Analytics\Config::default(),
        );
    }
}
