<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use pocketmine\command\Command as Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use SOFe\Capital\MainClass;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\PlayerInfo;
use SOFe\InfoAPI\StringInfo;

use function array_shift;
use function assert;

final class TopCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(MainClass $plugin, private Database $db, private TopCommandSpec $spec) {
        parent::__construct($spec->command, "TODO", "TODO");

        $permManager = PermissionManager::getInstance();
        $permManager->addPermission(new Permission($spec->permission));
        $root = $permManager->getPermission($spec->defaultOpOnly ? DefaultPermissions::ROOT_OPERATOR : DefaultPermissions::ROOT_USER);
        assert($root !== null, "Default permission root not registered");
        $root->addChild($spec->permission, true);

        $this->setPermission($spec->permission);
        $this->owningPlugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$this->testPermission($sender)) {
            return;
        }
        if($this->spec->requirePlayer && !($sender instanceof Player)) {
            $sender->sendMessage(TextFormat::RED . "This command can only be run by a player.");
            return;
        }

        $senderInfo = $sender instanceof Player ? new PlayerInfo($sender) : null;

        $players = [];
        $strings = [];

        foreach($this->spec->args as $arg) {
            $error = match($arg) {
                CommandArgsInfo::ARG_PLAYER => self::playerArg($args, $players),
                CommandArgsInfo::ARG_STRING => self::stringArg($args, $strings),
            };

            if($error instanceof Translatable) {
                $sender->sendMessage($error->prefix(TextFormat::RED));
                return;
            }
        }

        $argContext = new CommandArgsInfo($senderInfo, $players, $strings);

        Await::f2c(function() use($argContext, $sender) {
            $selector = $this->spec->labelSelector->transform($argContext);
            $groupBy = $this->spec->groupLabels;
            $descending = $this->spec->descending;
            $limit = $this->spec->limit;
            $orderingMetricName = $this->spec->orderingInfo;
            $orderingMetric = new Query($this->spec->target, $this->spec->labelSelector, $this->spec->infos[$this->spec->orderingInfo]);

            $otherMetrics = [];
            foreach($this->spec->infos as $name => $info) {
                if($name !== $orderingMetricName) {
                    $query = new Query($this->spec->target, $this->spec->labelSelector, $info);
                    $otherMetrics[$name] = match($this->spec->target) {
                        Query::TARGET_ACCOUNT => $query->getAccountMetric(),
                        Query::TARGET_TRANSACTION => $query->getTransactionMetric(),
                    };
                }
            }

            $top = yield from match($this->spec->target) {
                Query::TARGET_ACCOUNT => $this->db->aggregateTopAccounts($selector, $groupBy, $orderingMetric->getAccountMetric(), $descending, $orderingMetricName, $otherMetrics, $limit),
                Query::TARGET_TRANSACTION => $this->db->aggregateTopTransactions($selector, $groupBy, $orderingMetric->getTransactionMetric(), $descending, $orderingMetricName, $otherMetrics, $limit),
            };

            $sender->sendMessage(InfoAPI::resolve($this->spec->messages->header, $argContext));
            foreach($top as $i => $entry) {
                $dynamicInfo = new DynamicInfo($entry->getMetrics(), $entry->getGroupValues(), $i + 1, $argContext);

                $message = InfoAPI::resolve($this->spec->messages->main, $dynamicInfo);
                $sender->sendMessage($message);
            }
        });
    }

    /**
     * @param list<string> $args Command arguments.
     * @param list<PlayerInfo> $players The PlayerInfo list passed to CommandArgsInfo.
     */
    private static function playerArg(array &$args, array &$players) : ?Translatable {
        if(!isset($args[0])) {
            throw new InvalidCommandSyntaxException;
        }

        $playerName = array_shift($args);
        $player = Server::getInstance()->getPlayerByPrefix($playerName);
        if($player === null) {
            return KnownTranslationFactory::commands_generic_player_notFound();
        }

        $players[] = new PlayerInfo($player);
        return null;
    }


    /**
     * @param list<string> $args Command arguments.
     * @param list<StringInfo> $strings The StringInfo list passed to CommandArgsInfo.
     */
    private static function stringArg(array &$args, array &$strings) : void {
        if(!isset($args[0])) {
            throw new InvalidCommandSyntaxException;
        }

        $string = array_shift($args);
        $strings[] = new StringInfo($string);
    }
}
