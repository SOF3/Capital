<?php

declare(strict_types=1);

namespace SOFe\Capital;

use pocketmine\plugin\PluginBase;
use SOFe\AwaitStd\AwaitStd;

final class MainClass extends PluginBase {
    private static self $instance;

    public static function getInstance(): self {
        return self::$instance;
    }

    public AwaitStd $std;

    protected function onEnable(): void {
        self::$instance = $this;
        $this->std = AwaitStd::init($this);

        $this->saveResource("db.yml");

        Database\Mod::init();
        Player\Mod::init();
    }
}
