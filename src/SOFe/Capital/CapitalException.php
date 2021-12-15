<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Exception;
use InvalidArgumentException;

final class CapitalException extends Exception {
    public const SOURCE_UNDERFLOW = 1;
    public const DESTINATION_OVERFLOW = 2;
    public const NO_SUCH_ACCOUNT = 3;
    public const NO_SUCH_TRANSACTION = 4;
    public const ACCOUNT_LABEL_ALREADY_EXISTS = 5;
    public const ACCOUNT_LABEL_DOES_NOT_EXIST = 6;

    public function __construct(int $code, ?Exception $previous = null) {
        $message = match($code) {
            self::SOURCE_UNDERFLOW => "Source account resultant value is too low",
            self::DESTINATION_OVERFLOW => "Destination account resultant value is too high",
            self::NO_SUCH_ACCOUNT => "The account does not exist",
            self::NO_SUCH_TRANSACTION => "The transaction does not exist",
            self::ACCOUNT_LABEL_ALREADY_EXISTS => "The account already has this label",
            self::ACCOUNT_LABEL_DOES_NOT_EXIST => "The account does not have this label",
            default => throw new InvalidArgumentException("Invalid exception code"),
        };
        parent::__construct($message, $code, $previous);
    }
}
