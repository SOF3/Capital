<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Generator;
use SOFe\Capital\Config\Parser;
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
     * @param Schema $schema The default label schema.
     */
    public function __construct(
        public Schema $schema,
    ) {}

    /**
     * @return Generator<mixed, mixed, mixed, self>
     */
    public static function fromSingletonArgs(Raw $raw, TypeRegistry $registry) : Generator {
        return yield from $raw->loadConfig(self::class, function(Parser $config) use($registry) {
            false && yield;

            $config = $config->enter("schema", <<<'EOT'
                A "schema" tells Capital how to manage accounts for each player.
                For example, the "basic" schema only sets up one account for each player,
                while the "currency" schema lets you define multiple currencies and sets up one account for each currency for each player.
                EOT);
            $schema = $registry->build($config);

            return new self(
                schema: $schema,
            );
        });
    }
}
