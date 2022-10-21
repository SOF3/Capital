<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use AssertionError;
use Generator;
use pocketmine\player\Player;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\DynamicCommand;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\Schema;
use SOFe\Capital\TransactionQueryMetric;
use SOFe\InfoAPI\PlayerInfo;


final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /**
     * @param array<string, Single\PlayerInfoUpdater> $singleQueries
     * @param array<string, PlayerInfoCommand> $infoCommands
     * @param list<Top\Config> $topQueries
     */
    public function __construct(
        public array $singleQueries,
        public array $infoCommands,
        public array $topQueries,
    ) {
    }

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        /** @var Schema\Config $schemaConfig */
        $schemaConfig = yield from $raw->awaitConfigInternal(Schema\Config::class);
        $schema = $schemaConfig->schema;

        $analytics = $config->enter("analytics", <<<'EOT'
            Settings related to statistics display.
            EOT);


        $playerInfosConfig = $analytics->enter("player-infos", <<<'EOT'
            The InfoAPI infos for a player.
            An info is a number related to the wealth or activities of a player,
            e.g. the total amount of money, amount of money in a specific currency,
            average spending per day, total amount of money earned from a specific source, etc.

            After setting up infos, you can use them in the info-commands section below.
            EOT, $isNew);

        if ($isNew) {
            $playerInfosConfig->enter("money", "This is an example info that displays the total money of a player.");
        }

        $singleQueries = [];

        foreach ($playerInfosConfig->getKeys() as $key) {
            $infoConfig = $playerInfosConfig->enter($key, null);
            $singleQueries[$key] = self::parseSingleQuery($infoConfig, $schema, $key);
        }


        $infoCommandsConfig = $analytics->enter("info-commands", <<<'EOT'
            Commands that display information about a player.
            You can use the infos defined in the player-infos section above,
            as well as usual InfoAPI commands.
            EOT, $isNew);

        if ($isNew) {
            $infoCommandsConfig->enter("checkmoney", "This is an example command that checks the total money of a player using the {money} info defined above.");
        }

        $infoCommands = [];

        foreach ($infoCommandsConfig->getKeys() as $key) {
            $infoConfig = $infoCommandsConfig->enter($key, null);
            $infoCommands[$key] = self::parseInfoCommand($infoConfig, $key);
        }


        $topPlayersConfig = $analytics->enter("top-player-commands", <<<'EOT'
            A top-player command lets you create commands that discover the top players in a certain category.
            It provides the answer to questions like "who is the richest player?" or
            "who spent the most money last week?".
            EOT, $isNew);

        if ($isNew) {
            $topPlayersConfig->enter("richest", "This is an example top-player command that shows the richest players.");
        }

        $topQueries = [];

        foreach ($topPlayersConfig->getKeys() as $key) {
            $queryConfig = $topPlayersConfig->enter($key, null);
            $topQueries[] = Top\Config::parse($queryConfig, $schema, $key);
        }

        return new self(
            singleQueries: $singleQueries,
            infoCommands: $infoCommands,
            topQueries: $topQueries,
        );
    }

    private static function parseSingleQuery(Parser $infoConfig, Schema\Schema $schema, string $infoName) : Single\PlayerInfoUpdater {
        $type = $infoConfig->expectString("of", "account", <<<'EOT'
            The data source of this info.
            If set to "account", the info is calculated from statistics of some of the player's accounts.
            If set to "transaction", the info is calculated from statistics of the player's recent transactions.
            EOT);
        if ($type !== "account" && $type !== "transaction") {
            $type = $infoConfig->setValue("of", "account", "Expected \"account\" or \"transaction\"");
        }

        if ($type === "account") {
            $selectorConfig = $infoConfig->enter("selector", "Selects which accounts of the player to calculate.");
            $infoSchema = $schema->cloneWithCompleteConfig($selectorConfig);

            $metric = AccountQueryMetric::parseConfig($infoConfig, "metric");

            $query = new Single\AccountQuery($metric, fn(Player $player) => $infoSchema->getSelector($player));
        } elseif ($type === "transaction") {
            $selectorConfig = $infoConfig->enter("selector", "Filter transactions by labels");
            $labels = [];
            foreach ($selectorConfig->getKeys() as $labelKey) {
                $labels[$labelKey] = $selectorConfig->expectString($labelKey, "", null);
            }
            $labels = new ParameterizedLabelSelector($labels);

            $metric = TransactionQueryMetric::parseConfig($infoConfig, "metric");

            $query = new Single\TransactionQuery($metric, fn(Player $player) => $labels->transform(new PlayerInfo($player)));
        } else {
            throw new AssertionError("unreachable code");
        }

        $updateFrequencyTicks = (int) ($infoConfig->expectNumber("update-frequency", 5.0, <<<'EOT'
            The frequency in seconds at which the info is refreshed from the server.
            This will only affect displays and will not affect transactions.
            EOT) * 20.);

        return new Single\PlayerInfoUpdater($infoName, new Single\Cached($query, $updateFrequencyTicks));
    }

    private static function parseInfoCommand(Parser $config, string $cmdName) : PlayerInfoCommand {
        $command = DynamicCommand::parse($config, "analytics.single", $cmdName, "Check money of yourself or another player", [
            "self" => false,
            "other" => true,
        ]);
        $template = $config->expectString("format", '{name} has ${money}.', <<<EOT
            The format of command output.
            Use InfoAPI syntax here.
            EOT);

        return new PlayerInfoCommand($command, $template);
    }
}
