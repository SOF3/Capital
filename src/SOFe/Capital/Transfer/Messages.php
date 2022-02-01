<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\Config\Parser;

final class Messages {
    public static function parse(Parser $parser) : self
    {
        return new self (
            $parser->expectString("player-only-command", "{red}Only players may use this command.", "Sent to command sender if this command requires them to be player and they are not."),
            $parser->expectString("notify-sender-success", "{green}notify-sender-success (override me!)", "Sent to command sender on success."),
            $parser->expectString("notify-recipient-success", "{green}notify-recipient-success (override me!)", "Sent to recipient on success."),
            $parser->expectString("no-source-accounts", "{red}no-recipient-accounts (override me!)", "Sent when no source accounts are found."),
            $parser->expectString("no-destination-accounts", "{red}no-destination-accounts (override me!)", "Sent when no destination accounts are found."),
            $parser->expectString("underflow", "{red}underflow (override me!)", "Sent when too much money is withdrawn."),
            $parser->expectString("overflow", "{red}overflow (override me!)", "Sent when too much money is given."),
            $parser->expectString("internal-error", "{red}internal-error (override me!)", "Sent when an unexpected error occurs."),
        );
    }

    public function __construct(
        public string $playerOnlyCommand,
        public string $notifySenderSuccess,
        public string $notifyRecipientSuccess,
        public string $noSourceAccounts,
        public string $noDestinationAccounts,
        public string $underflow,
        public string $overflow,
        public string $internalError,
    ) {}
}
