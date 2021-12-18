<?php

declare(strict_types=1);

namespace SOFe\Capital;

trait SingletonTrait {
    public static function get(TypeMap $typeMap) : static {
        /** @var static $value */
        $value = $typeMap->get(static::class);
        return $value;
    }
}
