<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use RuntimeException;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\TransactionQueryMetric;

final class Query {
    public const TARGET_ACCOUNT = 0;
    public const TARGET_TRANSACTION = 1;

    public const METRIC_ACCOUNT_COUNT = 0;
    public const METRIC_ACCOUNT_BALANCE_SUM = 1;
    public const METRIC_ACCOUNT_BALANCE_MEAN = 2;
    public const METRIC_ACCOUNT_BALANCE_VARIANCE = 3;
    public const METRIC_ACCOUNT_BALANCE_MIN = 4;
    public const METRIC_ACCOUNT_BALANCE_MAX = 5;

    public const METRIC_TRANSACTION_COUNT = 0x1000;
    public const METRIC_TRANSACTION_SRC_COUNT = 0x1001;
    public const METRIC_TRANSACTION_DEST_COUNT = 0x1002;
    public const METRIC_TRANSACTION_DELTA_SUM = 0x1003;
    public const METRIC_TRANSACTION_DELTA_MEAN = 0x1004;
    public const METRIC_TRANSACTION_DELTA_VARIANCE = 0x1005;
    public const METRIC_TRANSACTION_DELTA_MIN = 0x1006;
    public const METRIC_TRANSACTION_DELTA_MAX = 0x1007;

    /**
     * @param self::TARGET_* $target The target object type of the query.
     * @param ParameterizedLabelSelector<CommandArgsInfo> $selector The selector to use for the query.
     * @param self::METRIC_* $metric The metric to use for the query.
     */
    public function __construct(
        public int $target,
        public ParameterizedLabelSelector $selector,
        public int $metric,
    ) {
        $isAccountTarget = $target === self::TARGET_ACCOUNT;
        $isAccountMetric = ($metric & 0x1000) === 0;

        if($isAccountTarget !== $isAccountMetric){
            throw new RuntimeException("Target and metric must both refer to accounts or both refer to transactions");
        }
    }

    public function getAccountMetric() : AccountQueryMetric {
        if($this->target !== self::TARGET_ACCOUNT) {
            throw new RuntimeException("Cannot call getAccountMetric on query targetting transactions");
        }

        return match($this->metric) {
            self::METRIC_ACCOUNT_COUNT => AccountQueryMetric::accountCount(),
            self::METRIC_ACCOUNT_BALANCE_SUM => AccountQueryMetric::balanceSum(),
            self::METRIC_ACCOUNT_BALANCE_MEAN => AccountQueryMetric::balanceMean(),
            self::METRIC_ACCOUNT_BALANCE_VARIANCE => AccountQueryMetric::balanceVariance(),
            self::METRIC_ACCOUNT_BALANCE_MIN => AccountQueryMetric::balanceMin(),
            self::METRIC_ACCOUNT_BALANCE_MAX => AccountQueryMetric::balanceMax(),
            default => throw new RuntimeException("Invalid account metric $this->metric"),
        };
    }

    public function getTransactionMetric() : TransactionQueryMetric {
        if($this->target !== self::TARGET_TRANSACTION) {
            throw new RuntimeException("Cannot call getTransactionMetric on query targetting accounts");
        }

        return match($this->metric) {
            self::METRIC_TRANSACTION_COUNT => TransactionQueryMetric::transactionCount(),
            self::METRIC_TRANSACTION_SRC_COUNT => TransactionQueryMetric::sourceCount(),
            self::METRIC_TRANSACTION_DEST_COUNT => TransactionQueryMetric::destinationCount(),
            self::METRIC_TRANSACTION_DELTA_SUM => TransactionQueryMetric::deltaSum(),
            self::METRIC_TRANSACTION_DELTA_MEAN => TransactionQueryMetric::deltaMean(),
            self::METRIC_TRANSACTION_DELTA_VARIANCE => TransactionQueryMetric::deltaVariance(),
            self::METRIC_TRANSACTION_DELTA_MIN => TransactionQueryMetric::deltaMin(),
            self::METRIC_TRANSACTION_DELTA_MAX => TransactionQueryMetric::deltaMax(),
            default => throw new RuntimeException("Invalid transaction metric $this->metric"),
        };
    }
}
