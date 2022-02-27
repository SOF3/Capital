<?php

declare(strict_types=1);

namespace SOFe\Capital;

/**
 * Standard transaction labels used by Capital itself.
 */
final class TransactionLabels {
    /**
     * Labels auxiliary transactions for unequal payment.
     * The value is the UUID of the actual transfer transaction.
     */
    public const UNEQUAL_AUX = "capital/unequalAux";
}
