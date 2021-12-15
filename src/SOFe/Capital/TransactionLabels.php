<?php

declare(strict_types=1);

namespace SOFe\Capital;

/**
 * Standard transaction labels used by Capital itself.
 */
final class TransactionLabels {
    /**
     * Labels oracle transactions for transfers with rate != 1.0.
     * The value is the UUID of the actual transfer transaction.
     */
    public const TRANSFER_ORACLE = "capital/transferOracle";
}
