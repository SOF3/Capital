<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Generator;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

/**
 * Settings related to players as account owners.
 */
final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /**
     * @param Schema $schema The default label schema.
     */
    public function __construct(
        public Schema $schema,
    ) {}

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        false && yield;

        $registry = yield from TypeRegistry::get($di);

        $config = $config->enter("schema", <<<'EOT'
            A "schema" tells Capital how to manage accounts for each player.
            For example, the "basic" schema only sets up one account for each player,
            while the "currency" schema lets you define multiple currencies and sets up one account for each currency for each player.
            EOT);
        $schema = $registry->build($config);

        return new self(
            schema: $schema,
        );
    }
}
