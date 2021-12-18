<?php

declare(strict_types=1);

namespace SOFe\SuiteTester;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use RuntimeException;
use SOFe\AwaitGenerator\Await as ShadedAwait;
use SOFe\AwaitStd\AwaitStd;

final class Main extends PluginBase {
    private static self $instance;

    public AwaitStd $std;

    public static function getInstance() : Main {
        return self::$instance;
    }

    public function onLoad() : void {
        self::$instance = $this;
    }

    public function onEnable() : void {
        $this->std = AwaitStd::init($this);

        $config = getenv("SUITE_TESTER_CONFIG") ?: ($this->getDataFolder() . "config.php");
        if(!is_file($config)) {
            throw new RuntimeException("\$SUITE_TESTER_CONFIG is not a file");
        }

        $output = getenv("SUITE_TESTER_OUTPUT") ?: ($this->getDataFolder() . "output.json");

        $steps = require $config;
        $steps = $steps();

        Await::f2c(function() use($steps, $output) {
            $i = 0;

            foreach($steps as $name => $step) {
                $this->getLogger()->info("Running step $name");

                file_put_contents($output, json_encode([
                    "ok" => false,
                    "step" => $i,
                    "totalSteps" => count($steps),
                ]));

                yield from $step();
            }

            $this->getLogger()->info("Finished all steps");

            file_put_contents($output, json_encode([
                "ok" => true,
                "step" => count($steps),
                "totalSteps" => count($steps),
            ]));

            Server::getInstance()->shutdown();
        });
    }
}

class_alias(ShadedAwait::class, Await::class);
