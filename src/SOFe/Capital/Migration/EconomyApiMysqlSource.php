<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use Generator;
use mysqli;
use mysqli_result;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config;
use stdClass;
use function is_float;
use function is_int;
use function is_string;

final class EconomyApiMysqlSource implements Source {
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $db,
        private float $multiplier,
    ) {
    }

    public static function parse(Config\Parser $config) : self {
        return new self(
            host: $config->expectString("host", "127.0.0.1", "Host of the MySQL database storing EconomyAPI data"),
            port: $config->expectInt("port", 3306, "Port of the MySQL database storing EconomyAPI data"),
            user: $config->expectString("user", "onebone", "User to access the MySQL database storing EconomyAPI data"),
            password: $config->expectString("password", "hello_world", "Password to access the MySQL database storing EconomyAPI data"),
            db: $config->expectString("db", "economyapi", "Schema name of the MySQL database storing EconomyAPI data"),
            multiplier: $config->expectNumber("multiplier", 1.0, <<<'EOT'
                The ratio to multiply each balance value by.
                Capital only supports integer balances,
                so if you want to preserve the decimal portions,
                you have to multiply them by 10 etc.
                EOT,
            ),
        );
    }

    public function generateEntries() : Generator {
        $db = new mysqli($this->host, $this->user, $this->password, $this->db, $this->port);
        if ($db->connect_error) {
            throw new ImportException("Cannot connect to database: {$db->connect_error}");
        }

        try {
            $result = $db->query("SELECT username, money FROM user_money");
            if (!($result instanceof mysqli_result)) {
                throw new ImportException("Cannot query database: {$db->error}");
            }

            try {
                while (true) {
                    /** @var stdClass|null|false $row */
                    $row = $result->fetch_object();
                    if ($row === false) {
                        throw new ImportException("Cannot fetch row: {$db->error}");
                    }
                    if ($row === null) {
                        break;
                    }

                    if (!is_string($row->username) || (!is_int($row->money) && !is_float($row->money))) {
                        throw new ImportException("EconomyAPI data is corrupted");
                    }

                    $intValue = (int) ($row->money * $this->multiplier);

                    yield new Entry($intValue, [
                        AccountLabels::MIGRATION_SOURCE => "economyapi",
                        AccountLabels::PLAYER_NAME => $row->username,
                    ]);
                }
            } finally {
                $result->close();
            }
        } finally {
            $db->close();
        }
    }
}
