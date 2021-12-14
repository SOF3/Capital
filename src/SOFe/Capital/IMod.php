<?php

declare(strict_types=1);

namespace SOFe\Capital;

interface IMod {
    public static function init() : void;

    public static function shutdown() : void;
}
