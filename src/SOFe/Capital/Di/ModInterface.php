<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Generator;

interface ModInterface {
    /**
     * @return VoidPromise
     */
    public static function init(Context $context) : Generator;

    public static function shutdown(Context $context) : void;
}
