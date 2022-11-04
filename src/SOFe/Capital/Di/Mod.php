<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";
}
