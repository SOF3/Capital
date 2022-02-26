<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Top;

use Generator;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\plugin\Plugin;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Analytics\PaginationInfo;
use SOFe\Capital\Config\DynamicCommand;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Schema;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use function intdiv;
use function is_numeric;
use function min;

final class Config {
    public function __construct(
        public DynamicCommand $command,
        public QueryArgs $queryArgs,
        public RefreshArgs $refreshArgs,
        public PaginationArgs $paginationArgs,
        public Messages $messages,
    ) {
    }

    public function register(Plugin $plugin, AwaitStd $std, DatabaseUtils $database) : void {
        $this->registerCommand($plugin, $database);
        Await::g2c(Mod::runRefreshLoop($this->queryArgs, $this->refreshArgs, $std, $database));
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

                $firstRank = ($page - 1) * $this->paginationArgs->perPage + 1;
                $paginationInfo = new PaginationInfo(
                    page: new NumberInfo($page),
                    totalPages: new NumberInfo($totalPages),
                    perPage: new NumberInfo($this->paginationArgs->perPage),
                    total: new NumberInfo($totalCount),
                    firstRank: new NumberInfo($firstRank),
                    lastRank: new NumberInfo(min($page * $this->paginationArgs->perPage, $totalCount)),
                );

                $sender->sendMessage(InfoAPI::resolve($this->messages->header, $paginationInfo));

                foreach ($analytics as $entry) {
                    $sender->sendMessage(InfoAPI::resolve($this->messages->entry, $entry->asInfo($firstRank)));
                }

                $sender->sendMessage(InfoAPI::resolve($this->messages->footer, $paginationInfo));
            });
        });
    }

    public static function parse(Parser $infoConfig, Schema\Schema $schema, string $cmdName) : self {
        $cmdConfig = $infoConfig->enter("command", "The command that displays the information.");
        $command = DynamicCommand::parse($cmdConfig, "analytics.top", $cmdName, "Displays the richest player", false);

        $queryArgs = QueryArgs::parse($infoConfig, $schema);

        $refreshConfig = $infoConfig->enter("refresh", <<<'EOT'
            Refresh settings for the top query.
            These settings depend on how many active accounts you have in the database
            as well as how powerful the CPU of your database server is.
            Try increasing the frequencies and reducing batch size if the database server is lagging.
            EOT);
        $refreshArgs = RefreshArgs::parse($refreshConfig);

        $paginationConfig = $infoConfig->enter("pagination", <<<'EOT'
            Pagination settings for the top query.
            EOT);
        $paginationArgs = PaginationArgs::parse($paginationConfig);

        return new Config(
            command: $command,
            queryArgs: $queryArgs,
            refreshArgs: $refreshArgs,
            paginationArgs: $paginationArgs,
            messages: Messages::parse($infoConfig->enter("messages", "Configures the displayed messages")),
        );
    }
}
