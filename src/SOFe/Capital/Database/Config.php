<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

/**
 * Settings related to players as account owners.
 */
final class Config {
    /**
     * @param array<string, mixed> $libasynql libasynql config.
     * @param bool $logQueries Whether to log queries to the console.
     */
    public function __construct(
        public array $libasynql,
        public bool $logQueries,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function load(array $config) : self {
        return new self(
            libasynql: $config,
            logQueries: true,
        );
    }
}
