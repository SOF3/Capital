<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use SOFe\InfoAPI\ContextInfo;
use SOFe\InfoAPI\PlayerInfo;

/**
 * The context info used to reify labels in a command-initiated transfer.
 */
final class InitialAccountLabelContextInfo extends ContextInfo{
    /** @var PlayerInfo $player The player to create account for. */
    public PlayerInfo $player;
}
