<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

final class Messages {
    public function __construct(
        public string $notifySenderSuccess,
        public string $notifyRecipientSuccess,
        public string $noSourceAccounts,
        public string $noDestinationAccounts,
        public string $underflow,
        public string $overflow,
        public string $internalError,
    ) {}
}
