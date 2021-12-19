<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

final class AccountQueryMetric {
    public static function accountCount() : self {
        return new self("COUNT(DISTINCT id)", true);
    }

    public static function balanceSum() : self {
        return new self("SUM(value)");
    }

    public static function balanceMean() : self {
        return new self("AVG(value)");
    }

    public static function balanceVariance() : self {
        return new self("AVG(value * value) - AVG(value) * AVG(value)");
    }

    public static function balanceMin() : self {
        return new self("MIN(value)");
    }

    public static function balanceMax() : self {
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
