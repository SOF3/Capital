<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

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
     * @param array<string, mixed> $libasynql libasynql config.
     * @param bool $logQueries Whether to log queries to the console.
     */
    public function __construct(
        public array $libasynql,
        public bool $logQueries,
    ) {}

    public static function parse(Parser $parser, Context $context) : Generator {
        $raw = yield from Raw::get($context);

        return new self(
            libasynql: $raw->dbConfig,
            logQueries: true,
        );
    }
}
