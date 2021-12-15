<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use function get_class;

use RuntimeException;
use SOFe\Capital\Config;
use SOFe\Capital\ModInterface;

final class Mod implements ModInterface {
    public static function init() : void {
        foreach(Config::getInstance()->transfer->transferMethods as $method) {
            $method->register();
        }
    }

    public static function shutdown() : void {}
}
