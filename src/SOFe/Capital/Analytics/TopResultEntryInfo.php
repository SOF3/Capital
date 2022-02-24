<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use pocketmine\Server;
use RuntimeException;
use SOFe\InfoAPI\CommonInfo;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\StringInfo;

final class TopResultEntryInfo extends Info {
    /** @var array<string, true> */
    private static $registered = [];

    /**
     * @param array<string, StringInfo> $displays
     */
    public function __construct(
        private NumberInfo $rank,
        private NumberInfo $value,
        private array $displays,
    ) {
        foreach ($displays as $key => $_) {
            if (!isset(self::$registered[$key])) {
                self::$registered[$key] = true;
                InfoAPI::provideInfo(self::class, StringInfo::class, "capital.analytics.top.$key", fn(TopResultEntryInfo $self) => $self->displays[$key] ?? null);
            }
        }
    }

    public function toString() : string {
        throw new RuntimeException("TopResultEntryInfo must not be returned as a provided info");
    }

    public static function initCommon() : void {
        InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.analytics.top.rank", fn(TopResultEntryInfo $self) => $self->rank);
        InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.analytics.top.value", fn(TopResultEntryInfo $self) => $self->value);

        InfoAPI::provideFallback(self::class, CommonInfo::class, fn($_) => new CommonInfo(Server::getInstance()));
    }
}
