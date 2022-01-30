<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use pocketmine\player\Player;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Schema;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\TransactionQueryMetric;

final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    public function __construct(
    ) {}

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator{
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

        if(count($infos->getKeys()) === 0) {
            $infos->enter("money", "This is an example info that displays the total money of a player.");
        }

        foreach($infos->getKeys() as $key) {
            $infoConfig = $infos->enter($key, "");

            $type = $infoConfig->expectString("of", "account", <<<'EOT'
                The data source of this info.
                If set to \"account\", the info is calculated from statistics of some of the player's accounts.
                If set to \"transactions\", the info is calculated from statistics of the player's recent transactions.
                EOT);
            if($type !== "account" && $type !== "transaction") {
                $type = $infoConfig->setValue($key, "account", "Expected \"account\" or \"transaction\"");
            }

            if($type === "account") {
                $accountConfig = $infoConfig->enter("selector", "Selects which accounts of the player to calculate.");
                $infoSchema = $schema->cloneWithConfig($accountConfig, true);
                $labelGetter = fn(?Player $player) => $infoSchema->getSelector($player);

                $metric = match($infoConfig->expectString("metric", "balance-sum", <<<'EOT'
                    The statistic used to combine multiple values.

                    Possible values:
                    - "account-count": The number of accounts selected.
                    - "balance-sum": The sum of the balances of the accounts selected.
                    - "balance-mean": The average balance of the accounts selected.
                    - "balance-variance": The variance of the balances of the accounts selected.
                    - "balance-min": The minimum balance of the accounts selected.
                    - "balance-max": The maximum balance of the accounts selected.
                    EOT)) {
                    "account-count" => AccountQueryMetric::accountCount(),
                    "balance-sum" => AccountQueryMetric::balanceSum(),
                    "balance-mean" => AccountQueryMetric::balanceMean(),
                    "balance-variance" => AccountQueryMetric::balanceVariance(),
                    "balance-min" => AccountQueryMetric::balanceMin(),
                    "balance-max" => AccountQueryMetric::balanceMax(),
                    default => null,
                };
                if($metric === null) {
                    $infoConfig->setValue("metric", "balance-sum", "Unknown account metric type");
                    $metric = AccountQueryMetric::balanceSum();
                }
            } else {
                // TODO implement
                $labelGetter = fn(Player $player) => ["foo" => "bar"];

                $findSrc = $infoConfig

                $metric = match($infoConfig->expectString("metric", "transaction-count", <<<'EOT'
                    T
                    EOT)) {
                    default => null,
                };
                if($metric === null) {
                    $infoConfig->setValue("metric", "transaction-count", "Unkknown transaction metric type");
                    $metric = TransactionQueryMetric::transactionCount();
                }
            }
        }

        return new self(

        );
    }
}
