<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\AwaitGenerator\Await;
use SOFe\Capital\ModInterface;

final class Mod implements ModInterface {
    public static function init() : void {
    }

    public static function shutdown() : void {}
}
