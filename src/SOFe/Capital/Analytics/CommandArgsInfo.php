<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use RuntimeException;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\PlayerInfo;
use SOFe\InfoAPI\StringInfo;

final class CommandArgsInfo extends Info {
    /** @var array<string, true> */
    private static array $registeredInfos = [];

    /**
     * @param list<PlayerInfo> $players
     * @param list<StringInfo> $strings
     */
    public function __construct(
        private ?PlayerInfo $sender,
        private array $players,
        private array $strings,
    ) {
        if(!isset(self::$registeredInfos["sender"])) {
            self::$registeredInfos["sender"] = true;

            InfoAPI::provideInfo(
                self::class, PlayerInfo::class, "capital.analytics.sender",
                static function(CommandArgsInfo $info) : ?PlayerInfo {
                    return $info->sender;
                },
            );
        }

        foreach([
            [
                "player", PlayerInfo::class, count($players),
                static function(CommandArgsInfo $info) : array {
                    return $info->players;
                },
            ],
            [
                "string", StringInfo::class, count($strings),
                static function(CommandArgsInfo $info) : array {
                    return $info->strings;
                },
            ],
        ] as [$name, $class, $count, $field]) {
            for($i = 1; $i <= $count; $i++) {
                $keys = [$name . $i];
                if($i === 1) {
                    $keys[] = $name;
                }

                foreach($keys as $key) {
                    if(isset(self::$registeredInfos[$key])) {
                        continue;
                    }

                    self::$registeredInfos[$key] = true;

                    InfoAPI::provideInfo(
                        self::class, $class, "capital.analytics.custom.$key",
                        static function(CommandArgsInfo $info) use($field, $i) {
                            $array = $field($info);
                            if(isset($array[$i - 1])) {
                                return $array[$i - 1];
                            } else {
                                return null;
                            }
                        },
                    );
                }
            }
        }
    }

    public function toString() : string {
        throw new RuntimeException("Context info cannot be displayed directly");
    }
}
