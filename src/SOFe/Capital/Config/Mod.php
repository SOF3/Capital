<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Generator;
use SOFe\Capital\MainClass;
use SOFe\Capital\TypeMap\ModInterface;
use SOFe\Capital\TypeMap\TypeMap;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(TypeMap $typeMap) : Generator {
        $std = MainClass::getStd($typeMap);
        yield from $std->sleep(0);

        $config = Config::default($typeMap);
        $event = new PopulateEvent($config);
        $event->call();

        $typeMap->store($event->getConfig());
    }

    public static function shutdown(TypeMap $typeMap) : void {
    }
}
