<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use SOFe\Capital\Database\DatabaseConfig;
use SOFe\Capital\Player\PlayerConfig;
use SOFe\Capital\Singleton;
use SOFe\Capital\SingletonTrait;
use SOFe\Capital\Transfer\TransferConfig;
use SOFe\Capital\TypeMap;

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

    public static function default(TypeMap $typeMap) : self {
        return new self(
            DatabaseConfig::default($typeMap),
            PlayerConfig::default(),
            TransferConfig::default(),
        );
    }
}
