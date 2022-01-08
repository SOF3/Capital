<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use Generator;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\ModInterface;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(Context $context) : Generator {
        $database = Database::get($context);
        yield from $database->init();
    }

    public static function shutdown(Context $context) : void {
        $database = Database::get($context);
        $database->shutdown();
    }
}
