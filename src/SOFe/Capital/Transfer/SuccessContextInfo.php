<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use RuntimeException;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;

/**
 * The context info used to reify labels in a command-initiated transfer.
 */
final class SuccessContextInfo extends Info {
    /**
     * @param NumberInfo $srcBalance the new amount of money in the source accounts
     * @param NumberInfo $destBalance The new amount of money in the destination accounts
     */
    public function __construct(
        private NumberInfo $srcBalance,
        private NumberInfo $destBalance,
        private ContextInfo $fallback,
    ) {}

    public function toString() : string {
		throw new RuntimeException("ContextInfo must not be returned as a provided info");
    }

    public static function init() : void {
        InfoAPI::provideInfo(self::class, PlayerInfo::class, "capital.transfer.srcBalance", fn($info) => $info->srcBalance);
        InfoAPI::provideInfo(self::class, PlayerInfo::class, "capital.transfer.destBalance", fn($info) => $info->destBalance);
        InfoAPI::provideFallback(self::class, ContextInfo::class, fn($info) => $info->fallback);
    }
}
