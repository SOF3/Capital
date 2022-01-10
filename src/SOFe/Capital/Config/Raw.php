<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\MainClass;
use function file_exists;
use function yaml_parse_file;

/**
 * Stores the raw config data. Should not be used directly except for config parsing.
 */
final class Raw implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /**
     * If this field is not null, config regeneration is requested.
     * Modules should try to populate this field using data from mainConfig if not null,
     * or the default value if mainConfig is null.
     *
     * @var array<string, mixed>|null
     */
    public ?array $saveConfig;

    /**
     * @param null|array<string, mixed> $mainConfig data from config.yml
     * @param array<string, mixed> $dbConfig data from db.yml
     */
    public function __construct(
        public ?array $mainConfig,
        public array $dbConfig,
    ) {
        if($mainConfig === null) {
            // need to generate new config
            $this->requestRegenerate();
        } else {
            $this->saveConfig = null;
        }
    }

    public static function fromSingletonArgs(MainClass $main) : self {
        if(file_exists($main->getDataFolder() . "config.yml")) {
            $mainConfig = yaml_parse_file($main->getDataFolder() . "config.yml");
        } else {
            $mainConfig = null;
        }

        $main->saveResource("db.yml");
        $dbConfig = yaml_parse_file($main->getDataFolder() . "db.yml");

        return new self(
            $mainConfig,
            $dbConfig,
        );
    }

    /**
     * Pre-initialize the `saveConfig` field if it is not already initialized.
     */
    public function requestRegenerate() : void {
        $this->saveConfig = $this->saveConfig ?? $this->mainConfig ?? [
            "#" => <<<'EOT'
                This is the main config file of Capital.
                You can change the values in this file to configure Capital.
                If you change some main settings that change the structure (e.g. schema), Capital will try its best
                to migrate your previous settings to the new structure and overwrite this file.
                The previous file will be stored in config.yml.old.
                EOT,
        ];
    }
}
