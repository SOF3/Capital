<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Generator;
use SOFe\Capital\Di\Context;

trait ConfigTrait {
    /**
     * @return Generator<mixed, mixed, mixed, self>
     */
    public static function fromSingletonArgs(Raw $raw, Context $context) : Generator {
        return yield from $raw->loadConfig(self::class);
    }

    /**
     * @return Generator<mixed, mixed, mixed, self>
     */
    public abstract static function parse(Parser $config, Context $context) : Generator;
}
