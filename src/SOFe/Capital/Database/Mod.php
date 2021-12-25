<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use Generator;
use SOFe\Capital\ModInterface;
use SOFe\Capital\TypeMap;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(TypeMap $typeMap) : Generator {
        $database = Database::get($typeMap);
        yield from $database->init();
    }

    public static function shutdown(TypeMap $typeMap) : void {
        $database = Database::get($typeMap);
        $database->shutdown();
    }
}
