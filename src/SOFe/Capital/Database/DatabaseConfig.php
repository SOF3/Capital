<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use SOFe\Capital\MainClass;
use SOFe\Capital\TypeMap;
use yaml_parse_file;

/**
 * Settings related to players as account owners.
 */
final class DatabaseConfig {
    /**
     * @param array<string, mixed> $libasynql libasynql config.
     * @param bool $logQueries Whether to log queries to the console.
     */
    public function __construct(
        public array $libasynql,
        public bool $logQueries,
    ) {}

    public static function default(TypeMap $typeMap) : self {
        $plugin = MainClass::get($typeMap);

        $config = yaml_parse_file($plugin->getDataFolder() . "db.yml");

        return new self(
            $config,
            true,
        );
    }
}
