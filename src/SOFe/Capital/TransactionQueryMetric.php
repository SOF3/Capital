<?php

declare(strict_types=1);

namespace SOFe\Capital;

final class TransactionQueryMetric {
    public static function transactionCount() : self {
        return new self("COUNT(DISTINCT id)", true);
    }

    public static function sourceCount() : self {
        return new self("COUNT(DISTINCT src)");
    }

    public static function destinationCount() : self {
        return new self("COUNT(DISTINCT dest)");
    }

    public static function deltaSum() : self {
        return new self("SUM(tran.value)");
    }

    public static function deltaMean() : self {
        return new self("AVG(tran.value)");
    }

    public static function deltaVariance() : self {
        return new self("AVG(tran.value * tran.value) - AVG(tran.value) * AVG(tran.value)");
    }

    public static function deltaMin() : self {
        return new self("MIN(tran.value)");
    }

    public static function deltaMax() : self {
        return new self("MAX(tran.value)");
    }

    private function __construct(
        private string $expr,
        private bool $usesIdOnly = false,
    ) {
    }

    public function getExpr() : string {
        return $this->expr;
    }

    public function usesIdOnly() : bool {
        return $this->usesIdOnly;
    }

    public static function parseConfig(Config\Parser $config, string $key) : self {
        $metricName = $config->expectString($key, "delta-sum", <<<'EOT'
            The statistic used to combine multiple values.

            Possible values:
            - "transaction-count": The number of transactions selected.
            - "src-count": The number of different source accounts.
            - "dest-count": The number of different destination accounts.
            - "delta-sum": The total capital flow in the transactions selected.
            - "delta-mean": The average transaction amount in the transactions selected.
            - "delta-variance": The variance of the transaction amounts of the transactions selected.
            - "delta-min": The smallest transaction amount in the transactions selected.
            - "delta-max": The largest transaction amount in the transactions selected.
            EOT);

        $metric = match ($metricName) {
            "transaction-count" => self::transactionCount(),
            "src-count" => self::sourceCount(),
            "dest-count" => self::destinationCount(),
            "delta-sum" => self::deltaSum(),
            "delta-mean" => self::deltaMean(),
            "delta-variance" => self::deltaVariance(),
            "delta-min" => self::deltaMin(),
            "delta-max" => self::deltaMax(),
            default => null,
        };

        if ($metric !== null) {
            return $metric;
        }

        $config->setValue($key, "delta-sum", "Invalid metric type $metricName");
        return self::deltaSum();
    }
}
