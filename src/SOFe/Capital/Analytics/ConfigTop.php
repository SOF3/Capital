<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use Generator;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\plugin\Plugin;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Config\DynamicCommand;
use SOFe\InfoAPI\AnonInfo;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\StringInfo;
use function bin2hex;
use function intdiv;
use function is_numeric;
use function min;
use function random_bytes;

final class ConfigTop {
    public function __construct(
        public DynamicCommand $command,
        public TopQueryArgs $queryArgs,
        public TopRefreshArgs $refreshArgs,
        public TopPaginationArgs $paginationArgs,
        public TopMessages $messages,
    ) {
    }

    public function register(Plugin $plugin, AwaitStd $std, DatabaseUtils $database) : void {
        $this->registerCommand($plugin, $database);
        Await::g2c($this->runRefreshLoop($std, $database));
    }

    private function registerCommand(Plugin $plugin, DatabaseUtils $database) : void {
        $this->command->register($plugin, function(CommandSender $sender, array $args) use ($database) : void {
            $page = isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : 1;
            if ($page <= 0) {
                throw new InvalidCommandSyntaxException;
            }

            Await::f2c(function() use ($database, $sender, $page) : Generator {
                $analytics = yield from $database->fetchTopAnalytics(
                    query: $this->queryArgs,
                    limit: $this->paginationArgs->limit,
                    page: $page,
                );

                $totalCount = min(yield from $database->fetchTopAnalyticsCount($this->queryArgs), $this->paginationArgs->limit);
                $totalPages = intdiv($totalCount - 1, $this->paginationArgs->limit) + 1;

                $paginationInfo = new PaginationInfo(
                    page: new NumberInfo($page),
                    totalPages: new NumberInfo($totalPages),
                    perPage: new NumberInfo($this->paginationArgs->perPage),
                    total: new NumberInfo($totalCount),
                    firstRank: new NumberInfo(($page - 1) * $this->paginationArgs->perPage + 1),
                    lastRank: new NumberInfo(min($page * $this->paginationArgs->perPage, $totalCount)),
                );

                $sender->sendMessage(InfoAPI::resolve($this->messages->header, $paginationInfo));

                $rank = ($page - 1) * $this->paginationArgs->perPage;
                foreach ($analytics as $name => $value) {
                    $rank += 1;

                    $sender->sendMessage(InfoAPI::resolve($this->messages->entry, new class("capital.analytics.top", [
                        "rank" => new NumberInfo($rank),
                        "name" => new StringInfo($name),
                        "value" => new NumberInfo($value),
                    ]) extends AnonInfo {
                    }));
                }

                $sender->sendMessage(InfoAPI::resolve($this->messages->footer, $paginationInfo));
            });
        });
    }

    /**
     * @return VoidPromise
     */
    private function runRefreshLoop(AwaitStd $std, DatabaseUtils $database) : Generator {
        while (true) {
            yield from $std->sleep(($this->refreshArgs->batchFrequency * 20));

            $runId = bin2hex(random_bytes(16));

            yield from $database->collect($runId, $this->queryArgs, $this->refreshArgs->expiry, $this->refreshArgs->batchSize);

            yield from $database->compute($runId, $this->queryArgs);
        }
    }
}
