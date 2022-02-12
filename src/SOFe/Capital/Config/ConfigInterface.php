<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Generator;
use SOFe\Capital\Di\Context;

interface ConfigInterface {
    /**
     * @return Generator<mixed, mixed, mixed, self>
     */
    public static function parse(Parser $config, Context $context, Raw $raw) : Generator;
}
