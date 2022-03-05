<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use AssertionError;
use Generator;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use function assert;
use function count;
use function serialize;
use function unserialize;

final class Mod implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const API_VERSION = "0.1.0";

    public static function fromSingletonArgs(Config $config, DatabaseUtils $database, MainClass $plugin, AwaitStd $std) : self {
        if ($config->source !== null) {
            Server::getInstance()->getCommandMap()->register("capital", new class($config->source, $database, $std, $plugin) extends Command implements PluginOwned {
                public function __construct(private Source $source, private DatabaseUtils $database, private AwaitStd $std, private Plugin $plugin) {
                    parent::__construct("capital-migrate", "Import player data", "/capital-migate");

                    $permission = new Permission("capital.migration.command");
                    $permManager = PermissionManager::getInstance();
                    $permManager->addPermission($permission);
                    $root = $permManager->getPermission(DefaultPermissions::ROOT_OPERATOR);
                    assert($root !== null, "Default permission root not registered");
                    $root->addChild($permission->getName(), true);

                    $this->setPermission($permission->getName());
                }

                public function getOwningPlugin() : Plugin {
                    return $this->plugin;
                }

                public function execute(CommandSender $sender, string $label, array $args) : void {
                    if (!$this->testPermission($sender)) {
                        return;
                    }

                    Await::f2c(function() use ($sender) {
                        $count = yield from Mod::migrate($this->source, $this->database, Server::getInstance(), $this->std);
                        $sender->sendMessage("Migration completed. Imported $count accounts.");
                    });
                }
            });
        }

        return new self;
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     */
    public static function migrate(Source $source, DatabaseUtils $database, Server $server, AwaitStd $std) : Generator {
        $buffer = new Buffer;

        $task = new class($source, $buffer) extends AsyncTask {
            private string $source;

            public function __construct(Source $source, Buffer $buffer) {
                $this->source = serialize($source);
                $this->storeLocal("buffer", $buffer);
            }

            public function onRun() : void {
                /** @var Source $source */
                $source = unserialize($this->source);
                foreach ($source->generateEntries() as $entry) {
                    $this->publishProgress($entry);
                }
            }

            public function onProgressUpdate($progress) : void {
                if (!($progress instanceof Entry)) {
                    throw new AssertionError("onProgressUpdate passed wrong type");
                }

                $this->fetchLocal("buffer")->data[] = $progress;
            }

            public function onCompletion() : void {
                $this->fetchLocal("buffer")->complete = true;
            }
        };
        $server->getAsyncPool()->submitTask($task);

        $updateCount = 0;

        while (true) {
            $complete = $buffer->complete;

            $data = $buffer->data;
            $buffer->data = [];

            if (count($data) === 0) {
                yield from $std->sleep(1);
            } else {
                $updateCount += count($data);

                yield from $database->addEntries($data);
            }

            if ($complete) {
                break;
            }
        }

        $event = new CompleteEvent($updateCount);
        $event->call();

        return $updateCount;
    }
}
