<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use pocketmine\Server;
use RuntimeException;
use SOFe\InfoAPI\CommonInfo;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

/**
 * The context info used to reify labels in a command-initiated transfer.
 */
final class ContextInfo extends Info {
    /**
     * @param ?PlayerInfo $sender The player who initiated the transfer.
     * @param PlayerInfo $recipient The player set as the recipient in the command.
     */
    public function __construct(
        private ?PlayerInfo $sender,
        private PlayerInfo $recipient,
        private NumberInfo $sentAmount,
        private NumberInfo $receivedAmount,
    ) {
    }

    public function toString() : string {
        throw new RuntimeException("ContextInfo must not be returned as a provided info");
    }

    public static function init() : void {
        InfoAPI::provideInfo(self::class, PlayerInfo::class, "capital.transfer.sender", fn($info) => $info->sender);
        InfoAPI::provideInfo(self::class, PlayerInfo::class, "capital.transfer.recipient", fn($info) => $info->recipient);

        InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.transfer.sentAmount", fn($info) => $info->sentAmount);
        InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.transfer.sent", fn($info) => $info->sentAmount);
        InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.transfer.amount", fn($info) => $info->sentAmount);

        InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.transfer.receivedAmount", fn($info) => $info->receivedAmount);
        InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.transfer.received", fn($info) => $info->receivedAmount);

        InfoAPI::provideFallback(self::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));
    }
}
