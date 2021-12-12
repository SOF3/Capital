<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

final class Config {
    private static ?self $instance = null;

    public static function getInstance() : self {
        return self::$instance ?? self::$instance = self::load();
    }

    /**
     * @param PlayerConfig $player Settings related to players as account owners.
     * @param TransferConfig $transfer Settings for the transfer module.
     */
    public function __construct(
        public PlayerConfig $player,
        public TransferConfig $transfer,
    ) {}

    public static function default() : self {
        return new self(
            PlayerConfig::default(),
            TransferConfig::default(),
        );
    }

    private static function load() : self {
        $config = self::default();
        // TODO load configs from disk
        return $config;
    }
}
