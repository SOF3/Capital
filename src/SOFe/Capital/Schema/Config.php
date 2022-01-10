<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Logger;
use SOFe\Capital\Config\ConfigException;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use function array_keys;
use function implode;

/**
 * Settings related to players as account owners.
 */
final class Config implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /**
     * @template V of object
     *
     * @param Schema<V> $schema The default label schema.
     */
    public function __construct(
        public Schema $schema,
    ) {}

    public static function fromSingletonArgs(Logger $logger, Raw $raw, TypeRegistry $registry) : self {
        $mainConfig = $raw->mainConfig;


        if($mainConfig !== null) {
            try {
                $schema = $registry->build($mainConfig["schema"]);
            } catch(ConfigException $ex) {
                $logger->warning("Invalid schema definition! {$ex->getMessage()}");
                $logger->notice("Regenerating config to adapt to new schema");

                $raw->requestRegenerate();

                $schema = $registry->defaultSchema($mainConfig["schema"]);
            }
        } else {
            $logger->notice("Default config not found, generating new config with basic schema");

            $raw->requestRegenerate();

            $schema = $registry->defaultSchema(["type" => "basic"]);
        }

        if($raw->saveConfig !== null) {
            $raw->saveConfig["schema"] = [
                "#" => <<<'EOT'
                    A "schema" tells Capital how to manage accounts for each player.
                    For example, the "basic" schema only sets up one account for each player,
                    while the "currency" schema lets you define multiple currencies and sets up one account for each currency for each player.
                    EOT,
                "#type" => "The type of schema. Possible values are \"" . implode("\", \"", array_keys($registry->getTypes())) . "\".",
                "type" => "basic",
            ];
        }

        return new self(
            schema: $schema,
        );
    }
}
