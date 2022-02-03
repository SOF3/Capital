<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\Config\Parser;

final class Messages {
    public static function parse(Parser $parser, ?self $default) : self
    {
        return new self (
            $parser->expectString("player-only-command", $default ?? "{red}Only players may use this command.", "Sent to command sender if this command requires them to be player and they are not."),
            $parser->expectString("notify-sender-success", $default?->notifySenderSuccess ?? "{green}notify-sender-success (override me!)", "Sent to command sender on success."),
            $parser->expectString("notify-recipient-success", $default?->notifyRecipientSuccess ?? "{green}notify-recipient-success (override me!)", "Sent to recipient on success."),
            $parser->expectString("no-source-accounts", $default?->noSourceAccounts ?? "{red}no-recipient-accounts (override me!)", "Sent when no source accounts are found."),
            $parser->expectString("no-destination-accounts", $default?->noDestinationAccounts ?? "{red}no-destination-accounts (override me!)", "Sent when no destination accounts are found."),
            $parser->expectString("underflow", $default?->underflow ?? "{red}underflow (override me!)", "Sent when too much money is withdrawn."),
            $parser->expectString("overflow", $default?->overflow ?? "{red}overflow (override me!)", "Sent when too much money is given."),
            $parser->expectString("internal-error", $default?->internalError ?? "{red}internal-error (override me!)", "Sent when an unexpected error occurs."),
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
