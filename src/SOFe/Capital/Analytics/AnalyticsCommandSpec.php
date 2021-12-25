<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use pocketmine\Server;
use SOFe\Capital\MainClass;

/**
 * View analytics by running a command.
 */
final class AnalyticsCommandSpec {
    public const ARG_STRING = "string";
    public const ARG_PLAYER = "player";

    /**
     * @param string $command The name of the command.
     * @param string $permission The permission for the command.
     * @param bool $defaultOpOnly Whether the permission is given to ops only by default.
     * @param bool $requirePlayer Whether the command can only be run as a player.
     * @param list<self::ARG_*> $args The argument types of the command.
     * @param array<string, AnalyticsQuery> $infos The query infos used in the main message.
     * @param ?TopNSpec $topN If not null, each $infos is grouped by $topNSpec->group, and $messages->main is repeated for up to $topNSpec->limit times for each of the top groups ordered by $args->[$topNSpec->order].
     * @param Messages $messages The messages to use.
     */
    public function __construct(
        public string $command,
        public string $permission,
        public bool $defaultOpOnly,
        public bool $requirePlayer,
        public array $args,
        public array $infos,
        public ?TopNSpec $topN,
        public Messages $messages,
    ) {}

    public function register(MainClass $plugin) : void {
        $command = new AnalyticsCommand($plugin, $this);
        Server::getInstance()->getCommandMap()->register("capital", $command);
    }
}
