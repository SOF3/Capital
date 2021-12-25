<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use RuntimeException;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;

final class DynamicInfo extends Info {
    /** @var array<string, true> */
    private static array $registeredInfos = [];

    /**
     * @param array<string, int|float> $values
     */
    public function __construct(
        private array $values,
        private ?int $rank,
        private CommandArgsInfo $args,
    ) {
        foreach($values as $key => $_) {
            if(isset(self::$registeredInfos[$key])) {
                continue;
            }

            self::$registeredInfos[$key] = true;

            InfoAPI::provideInfo(self::class, NumberInfo::class, "capital.analytics.custom.$key", static function(DynamicInfo $info) use($key) : ?NumberInfo {
                if(isset($info->values[$key])) {
                    return new NumberInfo((float) $info->values[$key]);
                } else {
                    return null;
                }
            });
        }

        if(!isset(self::$registeredInfos["\0rank"])) {
            self::$registeredInfos["\0rank"] = true;
            InfoAPI::provideInfo(
                self::class, CommandArgsInfo::class, "capital.analytics.rank",
                static function(DynamicInfo $info) : ?NumberInfo {
                    return $info->rank !== null ? new NumberInfo((float) $info->rank) : null;
                },
            );
        }

        if(!isset(self::$registeredInfos["\0args"])) {
            self::$registeredInfos["\0args"] = true;
            InfoAPI::provideFallback(
                self::class, CommandArgsInfo::class,
                static function(DynamicInfo $info) : CommandArgsInfo {
                    return $info->args;
                },
            );
        }
    }

    public function toString() : string {
        throw new RuntimeException("Context info cannot be displayed directly");
    }
}
