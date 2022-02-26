<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use function assert;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(Config $config, MainClass $plugin) : self {
        if ($config->source !== null) {
            Server::getInstance()->getCommandMap()->register("capital", new class($config->source, $plugin) extends Command implements PluginOwned {
                public function __construct(private Source $source, private Plugin $plugin) {
                    parent::__construct("capital-migrate", "Import player data", "/capital-migate");

                    $permission = new Permission("capital.migration.command");
                    $permManager = PermissionManager::getInstance();
                    $permManager->addPermission($permission);
                    $root = $permManager->getPermission(DefaultPermissions::ROOT_OPERATOR);
                    assert($root !== null, "Default permission root not registered");
                    $root->addChild($permission->getName(), true);

                    $this->setPermission($permission->getName());
                }

                public function getOwningPlugin(): Plugin {
                    return $this->plugin;
                }

                public function execute(CommandSender $sender, string $label, array $args) : void {
                    if (!$this->testPermission($sender)) {
                        return;
                    }

                    // TODO
                }
            });
        }

        return new self;
    }
}
