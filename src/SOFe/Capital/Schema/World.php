<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use pocketmine\player\Player;
use pocketmine\Server;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\LabelSet;

use function array_map;

/**
 * A schema where each player has one account for each world.
 */
final class World implements Schema {
    public const LABEL_WORLD = "capital/world";


    public static function build(Parser $globalConfig) : self {
        $defaultParser = $globalConfig->enter("default", <<<'EOT'
            The default account settings for each world not in `specific-worlds`
            EOT);
        $default = AccountConfig::parse($defaultParser);

        $specificWorlds = [];
        $specificWorldsParser = $globalConfig->enter("specific-worlds", <<<'EOT'
            World-specific account settings
            EOT);
        foreach ($specificWorldsParser->getKeys() as $world) {
            $specificWorldParser = $specificWorldsParser->enter($world, "this string should never appear");
            $specificWorlds[$world] = AccountConfig::parse($specificWorldParser);
        }

        return new self(false, null, $default, $specificWorlds);
    }

    public static function describe() : string {
        return "Each player has a new account in each world.";
    }

    /**
     * @param bool $canChoose Whether the player can choose to use another world they are not in.
     * @param string|null $defaultWorld Choose to use another world by default instead of the current world.
     * @param AccountConfig $defaultConfig Default AccountConfig for worlds not in `$specificWorldConfigs`.
     * @param array<string, AccountConfig> $specificWorldConfigs AccountConfigs for specific worlds.
     */
    public function __construct(
        private bool $canChoose,
        private ?string $defaultWorld,
        private AccountConfig $defaultConfig,
        private array $specificWorldConfigs,
    ) {
    }

    public function clone() : self {
        return clone $this;
    }

    public function cloneWithConfig(Parser $config) : self {
        $clone = clone $this;

        $clone->canChoose = $config->expectBool("allow-choose-world", false, <<<'EOT'
            Whether the player can choose to use the account in another world.
            The player must choose from one of the loaded worlds.
            EOT);
        $clone->defaultWorld = $config->expectNullableString("default-world", null, <<<'EOT'
            Use another world by default instead of the current world the player is in.
            Set to ~ to use the current world.
            If this is not ~, this should be the *folder* name of the world, not the level.dat name.
            This option works even if the world is not loaded.
            EOT);

        return $clone;
    }

    public function cloneWithCompleteConfig(Parser $config) : Complete {
        $clone = clone $this;

        $clone->defaultWorld = $config->expectNullableString("world", null, <<<'EOT'
            Use another world instead of the current world the player is in.
            Set to ~ to use the current world.
            If this is not ~, this should be the *folder* name of the world, not the level.dat name.
            This option works even if the world is not loaded.
            EOT);

        return new Complete($clone);
    }

    public function cloneWithInvariantConfig(Parser $config) : Invariant {
        $clone = clone $this;

        $clone->canChoose = false;
        $clone->defaultWorld = $config->expectString(
            key: "world",
            default: Server::getInstance()->getWorldManager()->getDefaultWorld()?->getFolderName() ?? "world",
            doc: <<<'EOT'
                The world to use.
                This should be the *folder* name of the world, not the level.dat name.
                This option works even if the world is not loaded.
                EOT);

        return new Invariant($clone);
    }

    public function isComplete() : bool {
        return true;
    }

    public function isInvariant() : bool {
        return $this->defaultWorld !== null;
    }

    public function getRequiredVariables() : iterable {
        return GeneratorUtil::empty();
    }

    public function getOptionalVariables() : iterable {
        if ($this->canChoose) {
            yield new Variable(
                type: Variable::TYPE_STRING,
                name: "world",
                populate: fn(World $schema, string $world) => $schema->defaultWorld = $world,
                enumValues: array_map(fn(\pocketmine\world\World $world) => $world->getFolderName(), Server::getInstance()->getWorldManager()->getWorlds()),
            );
        }
    }

    private function getWorld(Player $player) : string {
        return $this->defaultWorld ?? $player->getWorld()->getFolderName();
    }

    private function getAccountConfig(Player $player) : AccountConfig {
        return $this->specificWorldConfigs[$this->getWorld($player)] ?? $this->defaultConfig;
    }

    public function getSelector(Player $player) : ?LabelSelector {
        return new LabelSelector([
            AccountLabels::PLAYER_UUID => $player->getUniqueId()->toString(),
            self::LABEL_WORLD => $this->getWorld($player),
        ]);
    }

    public function getInvariantSelector() : ?LabelSelector {
        $world = $this->defaultWorld;
        if ($world === null) {
            return null;
        }

        return new LabelSelector([
            self::LABEL_WORLD => $world,
        ]);
    }

    public function getOverwriteLabels(Player $player) : ?LabelSet {
        return $this->getAccountConfig($player)->getOverwriteLabels($player);
    }

    public function getMigrationSetup(Player $player) : ?MigrationSetup {
        return $this->getAccountConfig($player)->getMigrationSetup($player);
    }

    public function getInitialSetup(Player $player) : ?InitialSetup {
        return $this->getAccountConfig($player)
            ->getInitialSetup($player)
            ->andInitialLabel(new LabelSet([
                self::LABEL_WORLD => $this->getWorld($player),
            ]));
    }
}
