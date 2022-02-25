<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use AssertionError;
use Closure;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permissible;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;
use function assert;
use function implode;
use function is_bool;

final class DynamicCommand {
    /**
     * @param string $name name of the command.
     * @param string $description description of the command.
     * @param string $permission permission required to use the command.
     * @param array<string, bool> $requiresOp whether each sub-permission requires op permission by default. key is sub-permission name, or empty string if there are no sub-permisisons.
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $permission,
        public array $requiresOp,
    ) {
    }


    /**
     * @param array<string, bool>|bool $requiresOp The default requires-op value for each sub-permission, or the value if there are no sub-permissions.
     */
    public static function parse(Parser $parser, string $module, string $name, string $description, array|bool $requiresOp) : self {
        $description = $parser->expectString("description", $description, "The description message of this command.");

        if (is_bool($requiresOp)) {
            $bool = $parser->expectBool("requires-op", $requiresOp, <<<'EOT'
                If set to true, only ops can use this command
                (you can further configure this with permission plugins).
                EOT);
            $requiresOpOutput = ["" => $bool];
        } else {
            $requiresOpOutput = [];
            foreach ($requiresOp as $perm => $bool) {
                $requiresOpOutput[$perm] = $parser->expectBool("$perm-requires-op", $bool, <<<EOT
                    If set to true, only ops can use this command for $perm
                    (you can further configure this with permission plugins).
                    EOT
                    );
            }
        }

        return new self($name, $description, "capital.$module.$name", $requiresOpOutput);
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

        $permissions = [];
        foreach ($this->requiresOp as $permission => $bool) {
            $name = $permission !== "" ? "{$this->permission}.{$permission}" : $this->permission;
            $permManager = PermissionManager::getInstance();
            $permManager->addPermission(new Permission($name));
            $root = $permManager->getPermission($bool ? DefaultPermissions::ROOT_OPERATOR : DefaultPermissions::ROOT_USER);
            assert($root !== null, "Default permission root not registered");
            $root->addChild($name, true);
            $permissions[] = $name;
        }

        $cmd->setPermission(implode(";", $permissions));

        Server::getInstance()->getCommandMap()->register("capital", $cmd);

        return $cmd;
    }

    public function checkSubPermission(Permissible $permissible, string $subPermission) : bool {
        if (!isset($this->requiresOp[$subPermission])) {
            throw new AssertionError("Call to checkSubPermission with undefined subpermission $subPermission");
        }

        return $permissible->hasPermission("{$this->permission}.{$subPermission}");
    }
}
