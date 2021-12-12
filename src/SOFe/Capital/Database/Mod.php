<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use SOFe\AwaitGenerator\Await;

final class Mod {
    public static function init() : void {
        Await::g2c(Database::getInstance()->init());
    }
}
