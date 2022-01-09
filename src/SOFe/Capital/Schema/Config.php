<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

/**
 * Settings related to players as account owners.
 */
final class Config implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /**
     * @template V of object
     *
     * @param Schema<V> $schema The default label schema.
     */
    public function __construct(
        public Schema $schema,
    ) {}

    public static function fromSingletonArgs(Raw $raw, TypeRegistry $registry) : self {
        if($raw->mainConfig !== null) {
            return new self(
                schema: $registry->build($raw->mainConfig["schema"]),
            );
        } else {
            $raw->saveConfig["schema"] = [
                "type" => "basic",
            ];
            return new self(
                schema: new Basic,
            );
        }
    }
}
