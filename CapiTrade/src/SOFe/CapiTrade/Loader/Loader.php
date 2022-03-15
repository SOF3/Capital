<?php

declare(strict_types=1);

namespace SOFe\CapiTrade\Loader;

use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;

use SOFe\Capital\Di\SingletonTrait;

final class Loader implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(
    ) : self {
        return new self;
    }
}
