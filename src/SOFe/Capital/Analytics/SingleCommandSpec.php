<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use pocketmine\Server;
use SOFe\Capital\MainClass;

/**
 * View metrics by running a command.
 *
 * SingleCommand supports using different selectors,
 * but it can only return a single value for each metric.
 * Useful for getting analytics about a specific player or the entire server.
 */
final class SingleCommandSpec {
    /**
     * @param string $command The name of the command.
     * @param string $permission The permission for the command.
     * @param bool $defaultOpOnly Whether the permission is given to ops only by default.
     * @param bool $requirePlayer Whether the command can only be run as a player.
     * @param list<CommandArgsInfo::ARG_*> $args The argument types of the command.
     * @param array<string, Query> $infos The query infos used in the main message.
     * @param SingleMessages $messages The messages to use.
     */
    public function __construct(
        public string $command,
        public string $permission,
        public bool $defaultOpOnly,
        public bool $requirePlayer,
        public array $args,
        public array $infos,
        public SingleMessages $messages,
    ) {}

    public function register(MainClass $plugin) : void {
        $command = new SingleCommand($plugin, $this);
        Server::getInstance()->getCommandMap()->register("capital", $command);
    }
}
