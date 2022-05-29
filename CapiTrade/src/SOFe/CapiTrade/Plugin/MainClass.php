<?php

declare(strict_types=1);

namespace SOFe\CapiTrade\Plugin;

use pocketmine\plugin\PluginBase;
use SOFe\Capital\Di;

final class MainClass extends PluginBase implements Di\Singleton {
    use Di\SingletonTrait;

    protected function onEnable() : void {
        \SOFe\Capital\Plugin\MainClass::$context->store($this);
        \SOFe\Capital\Loader\Loader::addEntryPoint(\SOFe\CapiTrade\Loader\Loader::class);
    }
}
