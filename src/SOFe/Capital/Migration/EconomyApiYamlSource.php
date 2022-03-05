<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use Generator;
use pocketmine\Server;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config;
use function is_float;
use function is_int;
use function yaml_parse_file;

final class EconomyApiYamlSource implements Source {
    public function __construct(
        private string $path,
        private float $multiplier,
    ) {
    }

    public function generateEntries() : Generator {
        $data = yaml_parse_file($this->path);

        if (!isset($data["version"]) && $data["version"] !== 2) {
            throw new ImportException("Only EconomyAPI YamlProvider v2 is supported");
        }

        if (!isset($data["money"])) {
            throw new ImportException("EconomyAPI data is corrupted");
        }

        $data = $data["money"];

        foreach ($data as $player => $amount) {
            $player = (string) $player;

            if (!is_int($amount) && !is_float($amount)) {
                throw new ImportException("EconomyAPI data is corrupted");
            }

            $intValue = (int) ($amount * $this->multiplier);

            yield new Entry($intValue, [
                AccountLabels::MIGRATION_SOURCE => "economyapi",
                AccountLabels::PLAYER_NAME => $player,
            ]);
        }
    }

    public static function parse(Config\Parser $config) : self {
        return new self(
            path: $config->expectString("path", Server::getInstance()->getDataPath() . "plugin_data/EconomyAPI/Money.yml", <<<'EOT'
                The path to the YAML file in EconomyAPI.
                EOT,
            ),
            multiplier: $config->expectNumber("multiplier", 1.0, <<<'EOT'
                The ratio to multiply each balance value by.
                Capital only supports integer balances,
                so if you want to preserve the decimal portions,
                you have to multiply them by 10 etc.
                EOT,
            ),
        );
    }
}
