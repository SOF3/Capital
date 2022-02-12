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
}
