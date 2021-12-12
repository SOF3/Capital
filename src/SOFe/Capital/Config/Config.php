<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

final class Config {
    /**
     * @param PlayerConfig $player Player-related settings.
     */
    public function __construct(
        public PlayerConfig $player,
        public UiConfig $ui,
    ) {}

    public static function defalut() : self {
        return new self(
            PlayerConfig::default(),
            UiConfig::default(),
        );
    }
}
