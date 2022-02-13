<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use AssertionError;
use RuntimeException;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\Schema\Schema;
use SOFe\Capital\TransactionQueryMetric;
use function get_class;
use function md5;
use function sort;

final class TopQueryArgs {
    public const ORDERING_ASC = "asc";
    public const ORDERING_DESC = "desc";

    /**
     * @param LabelSelector $labelSelector The labels that filter rows.
     * @param list<string> $groupingLabels The labels to group by.
     * @param list<string> $displayLabels The labels to display in the top list.
     * @param string $sortingLabel The label to sort by.
     * @param self::ORDERING_* $ordering Whether to sort ascendingly or descendingly.
     * @param AccountQueryMetric|TransactionQueryMetric $metric The metric used to aggregate the sorting label of accounts/transactions with the same grouping labels.
     *        The type of this parameter indicates whether to search accounts or transactions.
     */
    public function __construct(
        public LabelSelector $labelSelector,
        public array $groupingLabels,
        public array $displayLabels,
        public string $sortingLabel,
        public string $ordering,
        public AccountQueryMetric|TransactionQueryMetric $metric,
    ) {
    }

    /**
     * Serializes the query args into a unique byte array and hashes it using MD5,
     * yielding an output with always 32 hexadecimal characters.
     */
    public function hash() : string {
        $bytes = "";
        $bytes .= $this->labelSelector->toBytes();

        $bytes .= "\0";

        $groupingLabels = $this->groupingLabels;
        sort($groupingLabels);
        foreach ($groupingLabels as $label) {
            $bytes .= $label;
            $bytes .= "\0";
        }

        $bytes .= "\0";

        $displayLabels = $this->displayLabels;
        sort($displayLabels);
        foreach ($displayLabels as $label) {
            $bytes .= $label;
            $bytes .= "\0";
        }

        $bytes .= "\0";

        $bytes .= $this->sortingLabel;
        $bytes .= "\0";

        $bytes .= match ($this->ordering) {
            self::ORDERING_ASC => "\0",
            self::ORDERING_DESC => "\1",
        };

        $bytes .= match (get_class($this->metric)) {
            AccountQueryMetric::class => "\0",
            TransactionQueryMetric::class => "\1",
            default => throw new AssertionError("unreachable code"),
        };

        $bytes .= $this->metric->getExpr();

        return md5($bytes);
    }

    public static function parse(Parser $config, Schema $schema) : self {
        throw new RuntimeException("Not yet implemented");
    }
}
