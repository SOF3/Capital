<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Generator;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    /**
     * @return Generator<mixed, mixed, mixed, self>
     */
    public function fromSingletonArgs(Context $context, Raw $raw) : Generator {
        yield from $raw->loadAll($context);

        return new self;
    }
}
