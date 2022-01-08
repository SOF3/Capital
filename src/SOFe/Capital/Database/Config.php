<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use SOFe\Capital\MainClass;
use SOFe\Capital\TypeMap\TypeMap;
use function yaml_parse_file;

/**
 * Settings related to players as account owners.
 */
final class Config {
    /**
     * @param array<string, mixed> $libasynql libasynql config.
     * @param bool $logQueries Whether to log queries to the console.
     * @param int $accountArchiveTime The time in seconds after which an account is moved to the archive table.
     * @param int $accountArchiveInterval The interval in ticks between account archiving cycles.
     * @param bool $deleteAccountInsteadOfArchive Permanently delete accounts instead of archiving them. Does not affect existing archive.
     * @param int $transactionArchiveTime The time in seconds after which an transaction is moved to the archive table.
     * @param int $transactionArchiveInterval The interval in ticks between transaction archiving cycles.
     * @param bool $deleteTransactionInsteadOfArchive Permanently delete transactions instead of archiving them. Does not affect existing archive.
     */
    public function __construct(
        public array $libasynql,
        public bool $logQueries,
        public int $accountArchiveTime,
        public int $accountArchiveInterval,
        public bool $deleteAccountInsteadOfArchive,
        public int $transactionArchiveTime,
        public int $transactionArchiveInterval,
        public bool $deleteTransactionInsteadOfArchive,
    ) {}

    public static function default(TypeMap $typeMap) : self {
        $plugin = MainClass::get($typeMap);

        $config = yaml_parse_file($plugin->getDataFolder() . "db.yml");

        return new self(
            libasynql: $config,
            logQueries: true,
            accountArchiveTime: 86400 * 30, // 30 days
            accountArchiveInterval: 20 * 86400, // every day
            deleteAccountInsteadOfArchive: false,
            transactionArchiveTime: 86400 * 7, // 7 days
            transactionArchiveInterval: 20 * 86400, // every day
            deleteTransactionInsteadOfArchive: false,
        );
    }
}
