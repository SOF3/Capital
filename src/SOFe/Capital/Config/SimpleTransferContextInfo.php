<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use SOFe\InfoAPI\ContextInfo;
use SOFe\InfoAPI\PlayerInfo;

/**
 * The context info used to reify labels in a command-initiated transfer.
 */
final class SimpleTransferContextInfo extends ContextInfo{
    /** @var PlayerInfo $sender The player who initiated the transfer. */
    public PlayerInfo $sender;
    /** @var PlayerInfo $recipient The player set as the recipient in the command. */
    public PlayerInfo $recipient;
}
