<?php

declare(strict_types=1);

namespace SOFe\Capital;

use SOFe\Capital\Database\DatabaseConfig;
use SOFe\Capital\Player\PlayerConfig;
use SOFe\Capital\Transfer\TransferConfig;

final class Config implements Singleton {
    use SingletonTrait;

    /**
     * @param PlayerConfig $player Settings related to players as account owners.
     * @param TransferConfig $transfer Settings for the transfer module.
     */
    public function __construct(
        public DatabaseConfig $database,
        public PlayerConfig $player,
        public TransferConfig $transfer,
    ) {}

    private static function default(TypeMap $typeMap) : self {
        return new self(
            DatabaseConfig::default($typeMap),
            PlayerConfig::default(),
            TransferConfig::default(),
        );
    }

    public static function load(TypeMap $typeMap) : self {
        $config = self::default($typeMap);
        // TODO load configs from disk
        return $config;
    }
}
