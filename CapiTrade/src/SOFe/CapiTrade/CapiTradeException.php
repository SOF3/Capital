<?php

declare(strict_types=1);

namespace SOFe\CapiTrade;

use Exception;

final class CapiTradeException extends Exception {
    public const NO_SUCH_SHOP = 0;
    public const SHOP_LABEL_ALREADY_EXISTS = 1;
    public const SHOP_LABEL_DOES_NOT_EXIST = 2;
    public const EVENT_CANCELLED = 3;
    public const SHOP_HAS_NO_ACCOUNT = 4;
    public const TRANSACTION_FAILED = 5;

    /**
     * @param self::* $code
     */
    public function __construct(int $code, ?Exception $previous = null) {
        $message = match ($code) {
            self::NO_SUCH_SHOP => "The shop does not exist",
            self::SHOP_LABEL_ALREADY_EXISTS => "The shop already has this label",
            self::SHOP_LABEL_DOES_NOT_EXIST => "The shop does not have this label",
            self::EVENT_CANCELLED => "Event has been cancelled",
            self::SHOP_HAS_NO_ACCOUNT => "The shop is currently unavailable",
            self::TRANSACTION_FAILED => "The transaction failed",
        };
        parent::__construct($message, $code, $previous);
    }
}
