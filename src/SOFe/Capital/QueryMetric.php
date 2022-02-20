<?php

declare(strict_types=1);

namespace SOFe\Capital;

interface QueryMetric {
    public function getMainTable() : string;

    public function getLabelTable() : string;

    public function getExpr() : string;

    public function usesIdOnly() : bool;
}
