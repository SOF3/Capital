<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

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
     * @param array<string, mixed> $libasynql libasynql config.
     * @param bool $logQueries Whether to log queries to the console.
     */
    public function __construct(
        public array $libasynql,
        public bool $logQueries,
    ) {}

    public static function fromSingletonArgs(Raw $raw) : self {
        return new self(
            libasynql: $raw->dbConfig,
            logQueries: true,
        );
    }
}
