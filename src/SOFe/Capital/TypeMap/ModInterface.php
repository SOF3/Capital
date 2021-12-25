<?php

declare(strict_types=1);

namespace SOFe\Capital\TypeMap;

use Generator;

interface ModInterface {
    /**
     * @return VoidPromise
     */
    public static function init(TypeMap $typeMap) : Generator;

    public static function shutdown(TypeMap $typeMap) : void;
}
