<?php

declare(strict_types=1);

namespace SOFe\Capital;

final class AccountQueryMetric implements QueryMetric {
    public static function accountCount() : self {
        return new self("COUNT(DISTINCT id)", true);
    }

    public static function balanceSum() : self {
        return new self("SUM(capital_acc.value)");
    }

    public static function balanceMean() : self {
        return new self("AVG(capital_acc.value)");
    }

    public static function balanceVariance() : self {
        return new self("AVG(capital_acc.value * capital_acc.value) - AVG(capital_acc.value) * AVG(capital_acc.value)");
    }

    public static function balanceMin() : self {
        return new self("MIN(capital_acc.value)");
    }

    public static function balanceMax() : self {
        return new self("MAX(capital_acc.value)");
    }

    private function __construct(
        private string $expr,
        private bool $usesIdOnly = false,
    ) {
    }

    public function getMainTable() : string {
        return "capital_acc";
    }

    public function getLabelTable() : string {
        return "capital_acc_label";
    }

    public function getTimestampColumn() : string {
        return "capital_acc.touch";
    }

    public function getExpr() : string {
        return $this->expr;
    }

    public function usesIdOnly() : bool {
        return $this->usesIdOnly;
    }

    public static function parseConfig(Config\Parser $config, string $key) : self {
        $metricName = $config->expectString($key, "balance-sum", <<<'EOT'
            The statistic used to combine multiple values.

            Possible values:
            - "account-count": The number of accounts selected.
            - "balance-sum": The sum of the balances of the accounts selected.
            - "balance-mean": The average balance of the accounts selected.
            - "balance-variance": The variance of the balances of the accounts selected.
            - "balance-min": The minimum balance of the accounts selected.
            - "balance-max": The maximum balance of the accounts selected.
            EOT);

        $metric = match ($metricName) {
            "account-count" => self::accountCount(),
            "balance-sum" => self::balanceSum(),
            "balance-mean" => self::balanceMean(),
            "balance-variance" => self::balanceVariance(),
            "balance-min" => self::balanceMin(),
            "balance-max" => self::balanceMax(),
            default => null,
        };

        if ($metric !== null) {
            return $metric;
        }

        $config->setValue($key, "balance-sum", "Invalid metric type $metricName");
        return self::balanceSum();
    }
}
