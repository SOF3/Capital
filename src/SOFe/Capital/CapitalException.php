<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Exception;

final class CapitalException extends Exception {
    public const SOURCE_UNDERFLOW = 1;
    public const DESTINATION_OVERFLOW = 2;
    public const NO_SUCH_ACCOUNT = 3;
    public const NO_SUCH_TRANSACTION = 4;
    public const ACCOUNT_LABEL_ALREADY_EXISTS = 5;
    public const ACCOUNT_LABEL_DOES_NOT_EXIST = 6;
    public const TRANSACTION_LABEL_ALREADY_EXISTS = 7;
    public const TRANSACTION_LABEL_DOES_NOT_EXIST = 8;
    public const EVENT_CANCELLED = 9;

    /**
     * @param self::* $code
     */
    public function __construct(int $code, ?Exception $previous = null) {
        $message = match ($code) {
            self::SOURCE_UNDERFLOW => "Source account resultant value is too low",
            self::DESTINATION_OVERFLOW => "Destination account resultant value is too high",
            self::NO_SUCH_ACCOUNT => "The account does not exist",
            self::NO_SUCH_TRANSACTION => "The transaction does not exist",
            self::ACCOUNT_LABEL_ALREADY_EXISTS => "The account already has this label",
            self::ACCOUNT_LABEL_DOES_NOT_EXIST => "The account does not have this label",
            self::TRANSACTION_LABEL_ALREADY_EXISTS => "The transaction already has this label",
            self::TRANSACTION_LABEL_DOES_NOT_EXIST => "The transaction does not have this label",
            self::EVENT_CANCELLED => "The transaction event was cancelled",
        };
        parent::__construct($message, $code, $previous);
    }
}
