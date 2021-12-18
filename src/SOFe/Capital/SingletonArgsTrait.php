<?php

declare(strict_types=1);

namespace SOFe\Capital;

trait SingletonArgsTrait {
    public static function instantiate(TypeMap $typeMap) : static {
        /** @var static $value */
        $value = $typeMap->instantiate(static::class);
        return $value;
    }
}
