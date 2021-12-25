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
use SOFe\Capital\Capital;
use SOFe\Capital\MainClass;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\PlayerInfo;
use SOFe\InfoAPI\StringInfo;

use function array_shift;
use function assert;

final class SingleCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(MainClass $plugin, private SingleCommandSpec $spec) {
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
            $promises = [];

            foreach($this->spec->infos as $key => $info) {
                $selector = $info->selector->transform($argContext);

                // TODO merge metrics with the same selector to improve performance.
                $promise = function() use($info, $selector) {
                    [$metric] = yield from match($info->target) {
                        Query::TARGET_ACCOUNT => Capital::getAccountMetrics($selector, [$info->getAccountMetric()]),
                        Query::TARGET_TRANSACTION => Capital::getTransactionMetrics($selector, [$info->getTransactionMetric()]),
                    };

                    return $metric;
                };

                $promises[$key] = $promise();
            }

            $metrics = yield Await::all($promises);

            $dynamicInfo = new DynamicInfo($metrics, [], null, $argContext);

            $message = InfoAPI::resolve($this->spec->messages->main, $dynamicInfo);
            $sender->sendMessage($message);
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
