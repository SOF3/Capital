<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use pocketmine\Server;
use SOFe\Capital\Database\Database;
use SOFe\Capital\MainClass;
use SOFe\Capital\ParameterizedLabelSelector;

/**
 * View top groups by running a command.
 *
 * TopCommand supports listing the top N account/transaction groups,
 * but it only supports a single account selector.
 * Useful for listing the top players, top industries, etc.
 */
final class TopCommandSpec {
    /**
     * @param string $command The name of the command.
     * @param string $permission The permission for the command.
     * @param bool $defaultOpOnly Whether the permission is given to ops only by default.
     * @param bool $requirePlayer Whether the command can only be run as a player.
     * @param list<CommandArgsInfo::ARG_*> $args The argument types of the command.
     * @param ParameterizedLabelSelector<CommandArgsInfo> $labelSelector The label selector for the command. Does not include the label used for grouping.
     * @param list<string> $groupLabels The labels used for grouping results.
     * @param Query::TARGET_* $target The target table of the query.
     * @param array<string, Query::METRIC_*> $infos The metric infos used in the main message.
     * @param string $orderingInfo The metric used for ordering. Must be one of the keys in $infos.
     * @param bool $descending Whether the ordering is descending.
     * @param int $limit The maximum number of results to show.
     * @param TopMessages $messages The messages to use.
     */
    public function __construct(
        public string $command,
        public string $permission,
        public bool $defaultOpOnly,
        public bool $requirePlayer,
        public array $args,
        public ParameterizedLabelSelector $labelSelector,
        public array $groupLabels,
        public int $target,
        public array $infos,
        public string $orderingInfo,
        public bool $descending,
        public int $limit,
        public TopMessages $messages,
    ) {}

    public function register(MainClass $plugin, Database $db) : void {
        $command = new TopCommand($plugin, $db, $this);
        Server::getInstance()->getCommandMap()->register("capital", $command);
    }
}
