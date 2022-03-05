<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

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

        $sourceConfig = $migration->enter("source", <<<'EOT'
            The data source where the data to migrate are located.
            The data are not loaded until you run the `/capital-migrate` command.
            EOT);
        $source = match ($sourceConfig->expectString("plugin", "economyapi", <<<'EOT'
            The plugin to import from.

            Possible values:
            - economyapi
            EOT)) {
            "economyapi" => self::parseEconomyApiSource($sourceConfig),
            default => $migration->setValueAnd("source", "economyapi", "Invalid source plugin", fn() => self::parseEconomyApiSource($sourceConfig)),
        };

        return new self($source);
    }

    private static function parseEconomyApiSource(Parser $config) : Source {
        return match ($config->expectString("provider", "yaml", <<<'EOT'
            The provider type in EconomyAPI/config.yml

            Possible values:
            - "yaml": The (default) YAML data provider in EconomyAPI. The path to Money.yml should be specified below.
            - "mysql": The MySQL data provider in EconomyAPI. The connection details should be specified below.
            EOT)) {
            "yaml" => EconomyApiYamlSource::parse($config),
            "mysql" => EconomyApiMysqlSource::parse($config),
            default => $config->setValueAnd("provider", "yaml", "Invalid provider type", fn() => EconomyApiYamlSource::parse($config)),
        };
    }
}
