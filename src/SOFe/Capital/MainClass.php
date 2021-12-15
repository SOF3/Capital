<?php

declare(strict_types=1);

namespace SOFe\Capital;

use pocketmine\plugin\PluginBase;
use SOFe\AwaitStd\AwaitStd;

use function array_reverse;

final class MainClass extends PluginBase {
    private static self $instance;

    public static function getInstance(): self {
        return self::$instance;
    }

    public AwaitStd $std;

    /** @var list<class-string<ModInterface>> */
    public const MODULES = [
        Database\Mod::class,
        Player\Mod::class,
        Transfer\Mod::class,
    ];

    protected function onEnable(): void {
        self::$instance = $this;
        $this->std = AwaitStd::init($this);

        $this->saveResource("db.yml");

        foreach(self::MODULES as $module) {
            $module::init();
        }
    }

    protected function onDisable(): void {
        foreach(array_reverse(self::MODULES) as $module) {
            $module::shutdown();
        }
    }
}
