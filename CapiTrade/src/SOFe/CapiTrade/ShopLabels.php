<?php

declare(strict_types=1);

namespace SOFe\CapiTrade;

/**
 * Standard shop labels used by CapiTrade itself.
 */
final class ShopLabels {
    /** The parameterized label set for the transaction, encoded in JSON, parameterized by customer's PlayerInfo. */
    public const TRANSACTION_LABELS = "capitrade/transactionLabels";
}
