<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use Generator;
use pocketmine\Server;
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

    public function __construct(
        public ?Source $source,
    ) {
    }

    public static function parse(Parser $config, Context $context, Raw $raw) : Generator {
        false && yield;

        $migration = $config->enter("migration", <<<'EOT'
            The "migration" module lets you import data from other economy plugins into Capital.
            The import happens when you run the `/capital-migrate` command.
            EOT);

        if (!$migration->expectBool("enabled", true, <<<'EOT'
            Whether to enable the migration command.
            EOT)) {
            return new self(null);
        }

        return new self( match ($migration->expectString("source", "economyapi", <<<'EOT'
            The plugin to import from.

            Possible values:
            - economyapi
            EOT)) {
            "economyapi" => self::parseEconomyApiSource($migration),
            default => $migration->setValueAnd("source", "economyapi", "Invalid source plugin", fn() => self::parseEconomyApiSource($migration)),
        });
    }

    private static function parseEconomyApiSource(Parser $config) : Source {
        return match ($config->expectString("provider", "yaml", <<<'EOT'
            The provider type in EconomyAPI/config.yml

            Possible values:
            - "yaml": The (default) YAML data provider in EconomyAPI. The path to Money.yml should be specified below.
            - "mysql": The MySQL data provider in EconomyAPI. The connection details should be specified below.
            EOT)) {
            "yaml" => self::parseEconomyApiYamlSource($config),
            "mysql" => self::parseEconomyApiMysqlSource($config),
            default => $config->setValueAnd("provider", "yaml", "Invalid provider type", fn() => self::parseEconomyApiYamlSource($config)),
        };
    }

    private static function parseEconomyApiYamlSource(Parser $config) : Source {
        return new EconomyApiYamlSource(
            path: $config->expectString("path", Server::getInstance()->getDataPath() . "plugin_data/EconomyAPI/Money.yml", <<<'EOT'
                The path to the YAML file in EconomyAPI.
                EOT),
        );
    }

    private static function parseEconomyApiMysqlSource(Parser $config) : Source {
        return new EconomyApiMysqlSource(
            host: $config->expectString("host", "127.0.0.1", "Host of the MySQL database storing EconomyAPI data"),
            port: $config->expectInt("port", 3306, "Port of the MySQL database storing EconomyAPI data"),
            user: $config->expectString("user", "onebone", "User to access the MySQL database storing EconomyAPI data"),
            password: $config->expectString("password", "hello_world", "Password to access the MySQL database storing EconomyAPI data"),
            db: $config->expectString("db", "economyapi", "Schema name of the MySQL database storing EconomyAPI data"),
        );
    }
}
