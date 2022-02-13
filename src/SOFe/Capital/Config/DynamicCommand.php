<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Closure;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;
use function assert;

final class DynamicCommand {
    /**
     * @param string $name name of the command.
     * @param string $description description of the command.
     * @param string $permission permission required to use the command.
     * @param bool $requiresOp whether the command requires op permission by default.
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $permission,
        public bool $requiresOp,
    ) {
    }

    public static function parse(Parser $parser, string $module, string $name, string $description, bool $requiresOp) : self {
        $description = $parser->expectString("description", $description, "The description message of this command.");

        $permission = $parser->expectString("permission", "capital.$module.$name", "The permission required to execute this command.");

        $requiresOp = $parser->expectBool("requires-op", $requiresOp, <<<'EOT'
            If set to true, only ops can use this command
            (you can further configure this with permission plugins).
            EOT);

        return new self($name, $description, $permission, $requiresOp);
    }

    /**
     * @param Closure(CommandSender, list<string>): void $executor
     */
    public function register(Plugin $plugin, Closure $executor) : Command {
        $cmd = new class($this, $executor, $plugin) extends Command implements PluginOwned {
            public function __construct(
                DynamicCommand $command,
                private Closure $closure,
                private Plugin $plugin,
            ) {
                parent::__construct($command->name, $command->description, "TODO");
            }

            public function execute(CommandSender $sender, string $commandLabel, array $args) : void {
                if (!$this->testPermission($sender)) {
                    return;
                }

                ($this->closure)($sender, $args);
            }

            public function getOwningPlugin() : Plugin {
                return $this->plugin;
            }
        };

        $permManager = PermissionManager::getInstance();
        $permManager->addPermission(new Permission($this->permission));
        $root = $permManager->getPermission($this->requiresOp ? DefaultPermissions::ROOT_OPERATOR : DefaultPermissions::ROOT_USER);
        assert($root !== null, "Default permission root not registered");
        $root->addChild($this->permission, true);

        $cmd->setPermission($this->permission);

        Server::getInstance()->getCommandMap()->register("capital", $cmd);

        return $cmd;
    }
}
