<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

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
        return new self("SUM(value)");
    }

    public static function deltaMean() : self {
        return new self("AVG(value)");
    }

    public static function deltaVariance() : self {
        return new self("AVG(value * value) - AVG(value) * AVG(value)");
    }

    public static function deltaMin() : self {
        return new self("MIN(value)");
    }

    public static function deltaMax() : self {
        return new self("MAX(value)");
    }

    private function __construct(
        private string $expr,
        private bool $usesIdOnly = false,
    ) {}

    public function getExpr() : string {
        return $this->expr;
    }

    public function usesIdOnly() : bool {
        return $this->usesIdOnly;
    }
}
