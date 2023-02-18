<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Top;

use pocketmine\utils\Binary;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\QueryMetric;
use SOFe\Capital\Schema\Schema;
use function get_class;
use function md5;
use function sort;

/**
 * Queries the top label values.
 *
 * The query studies the accounts/transactions matching selector $labelSelector
 * and with the label $groupingLabel.
 * Whether the study target is an account or a transaction
 * is determined by the type of $metric.
 * For each distinct value of the label $groupingLabel in the matching accounts/transactions,
 * the statistic specified by $metric is computed for accounts/transactions with this label value.
 */
final class QueryArgs {
    public const ORDERING_ASC = "asc";
    public const ORDERING_DESC = "desc";

    private ?string $hash = null;

    /**
     * @param LabelSelector $labelSelector The labels that filter the accounts/transactions.
     * @param string $groupingLabel The label to group by.
     * @param array<string, string> $displayLabels The labels to display in the top list. Keys are InfoAPI info names and values are the labels to display.
     * @param self::ORDERING_* $ordering Whether to sort ascendingly or descendingly.
     * @param QueryMetric $metric The metric used to aggregate the sorting label of accounts/transactions with the same grouping label.
     *     The type of this parameter indicates whether to search accounts or transactions.
     */
    public function __construct(
        public LabelSelector $labelSelector,
        public string $groupingLabel,
        public array $displayLabels,
        public string $ordering,
        public ?int $interval,
        public QueryMetric $metric,
    ) {
    }

    /**
     * Serializes the query args into a unique byte array and hashes it using MD5,
     * yielding an output with always 32 hexadecimal characters.
     */
    public function hash() : string {
        if ($this->hash !== null) {
            return $this->hash;
        }

        $bytes = "";
        $bytes .= $this->labelSelector->toBytes();

        $bytes .= "\0";

        $bytes .= $this->groupingLabel;
        $bytes .= "\0";

        if ($this->interval !== null) {
            $bytes .= "\1" . Binary::writeLong($this->interval);
        } else {
            $bytes .= "\0";
        }

        $bytes .= get_class($this->metric);
        $bytes .= "\0";

        $bytes .= $this->metric->getExpr();

        return md5($bytes);
    }

    public static function parse(Parser $config, Schema $schema) : self {
        // For now, we only support accounts in the config
        // because it is complex to explain transactions to the user.
        // However, transactions are still a part of the API.

        $schema = $schema->cloneWithInvariantConfig($config->enter("selector", "Selects which accounts of each player to calculate."));
        $groupingLabel = AccountLabels::PLAYER_UUID;
        $displayLabels = ["name" => AccountLabels::PLAYER_NAME];
        /** @var self::ORDERING_* $ordering */
        $ordering = match ($config->expectString("ordering", self::ORDERING_DESC, <<<'EOT'
            Whether to sort results ascendingly or descendingly.
            Use "asc" for ascending sort and "desc" for descending sort.
            EOT)) {
            self::ORDERING_ASC => self::ORDERING_ASC,
            self::ORDERING_DESC => self::ORDERING_DESC,
            default => $config->setValue("ordering", self::ORDERING_DESC, "Invalid ordering"),
        };
        $interval = $config->expectNullableNumber("interval", 24, <<<'EOT'
            Number of hours of data to consider.
            Only accounts used in this interval or transactions that took place in this interval are considered.
            EOT);
        $interval = $interval !== null ? (int) ($interval * 3600) : null;
        $metric = AccountQueryMetric::parseConfig($config, "metric");

        return new self(
            labelSelector: $schema->getSelector(),
            groupingLabel: $groupingLabel,
            displayLabels: $displayLabels,
            ordering: $ordering,
            interval: $interval,
            metric: $metric,
        );
    }
}
