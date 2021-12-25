<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use RuntimeException;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\StringInfo;
use function count;

final class DynamicInfo extends Info {
    /** @var array<string, true> */
    private static array $registeredInfos = [];

    /**
     * @param array<string, int|float> $values
     * @param list<string> $groups
     */
    public function __construct(
        private array $values,
        private array $groups,
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

        for($i = 1; $i <= count($groups); $i++) {
            if(!isset(self::$registeredInfos["\0group$i"])) {
                self::$registeredInfos["\0group$i"] = true;

                $closure = static function(DynamicInfo $info) use($i) : ?StringInfo {
                    if(isset($info->groups[$i - 1])) {
                        return new StringInfo($info->groups[$i - 1]);
                    } else {
                        return null;
                    }
                };

                InfoAPI::provideInfo(self::class, StringInfo::class, "capital.analytics.group.group$i", $closure);
                if($i === 1) {
                    InfoAPI::provideInfo(self::class, StringInfo::class, "capital.analytics.group.group", $closure);
                }
            }
        }

        if(!isset(self::$registeredInfos["\0rank"])) {
            self::$registeredInfos["\0rank"] = true;
            InfoAPI::provideInfo(
                self::class, CommandArgsInfo::class, "capital.analytics.group.rank",
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
