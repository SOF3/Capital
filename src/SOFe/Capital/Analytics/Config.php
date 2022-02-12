<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use AssertionError;
use Generator;
use pocketmine\player\Player;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
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

use function count;

final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /**
     * @param array<string, Query<Player>> $singleQueries
     */
    public function __construct(
        public array $singleQueries,
    ) {
    }

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        /** @var Schema\Config $schemaConfig */
        $schemaConfig = yield from $raw->awaitConfigInternal(Schema\Config::class);
        $schema = $schemaConfig->schema;

        $analytics = $config->enter("analytics", <<<'EOT'
            Settings related to statistics display.
            EOT);

        $infos = $analytics->enter("player-infos", <<<'EOT'
            The InfoAPI infos for a player.
            An info is a number related to the wealth or activities of a player,
            e.g. the total amount of money, amount of money in a specific currency,
            average spending per day, total amount of money earned from a specific source, etc.
            EOT);

        if (count($infos->getKeys()) === 0) {
            $infos->enter("money", "This is an example info that displays the total money of a player.");
        }

        $queries = [];

        foreach ($infos->getKeys() as $key) {
            $infoConfig = $infos->enter($key, "");

            $type = $infoConfig->expectString("of", "account", <<<'EOT'
                The data source of this info.
                If set to \"account\", the info is calculated from statistics of some of the player's accounts.
                If set to \"transactions\", the info is calculated from statistics of the player's recent transactions.
                EOT);
            if ($type !== "account" && $type !== "transaction") {
                $type = $infoConfig->setValue($key, "account", "Expected \"account\" or \"transaction\"");
            }

            if ($type === "account") {
                $selectorConfig = $infoConfig->enter("selector", "Selects which accounts of the player to calculate.");
                $infoSchema = $schema->cloneWithConfig($selectorConfig, true);

                $metric = AccountQueryMetric::parseConfig($infoConfig, "metric");

                $queries[$key] = new AccountQuery($metric, fn(Player $player) => $infoSchema->getSelector($player));
            } elseif($type === "transaction") {
                $selectorConfig = $infoConfig->enter("selector", "Filter transactions by labels");
                $labels = [];
                foreach($selectorConfig->getKeys() as $labelKey) {
                    $labels[$labelKey] = $selectorConfig->expectString($key, "", null);
                }
                $labels = new ParameterizedLabelSelector($labels);

                $metric = TransactionQueryMetric::parseConfig($infoConfig, "metric");

                $queries[$key] = new TransactionQuery($metric, fn(Player $player) => $labels->transform(new PlayerInfo($player)));
            } else {
                throw new AssertionError("unreachable code");
            }
        }

        return new self(
            singleQueries: $queries,
        );
    }
}
