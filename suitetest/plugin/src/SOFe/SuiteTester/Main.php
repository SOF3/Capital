<?php

declare(strict_types=1);

namespace SOFe\SuiteTester;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use RuntimeException;
use SOFe\AwaitGenerator\Await as ShadedAwait;
use SOFe\AwaitStd\AwaitStd;
use stdClass;

final class Main extends PluginBase {
    private static self $instance;

    public static AwaitStd $std;

    public static function getInstance() : Main {
        return self::$instance;
    }

    public function onLoad() : void {
        self::$instance = $this;
    }

    public function onEnable() : void {
        global $timeout;

        self::$std = AwaitStd::init($this);

        $config = getenv("SUITE_TESTER_CONFIG") ?: ($this->getDataFolder() . "config.php");
        if(!is_file($config)) {
            throw new RuntimeException("\$SUITE_TESTER_CONFIG is not a file");
        }

        $output = getenv("SUITE_TESTER_OUTPUT") ?: ($this->getDataFolder() . "output.json");

        touch($output);
        if(!is_writable($output)) {
            throw new RuntimeException("$output is not writable");
        }

        $steps = iterator_to_array((require $config)());

        $timeout = (int) ($GLOBALS["timeout"] ?? 1200);

        Await::f2c(function() use($steps, $output, $timeout) {
            $i = 0;

            foreach($steps as $name => $step) {
                $this->getLogger()->notice("Running step: $name");

                file_put_contents($output, json_encode([
                    "ok" => false,
                    "step" => $i,
                    "totalSteps" => count($steps),
                ]));

                $timeoutIdentity = new stdClass;
                $timeoutReturn = yield from self::$std->timeout($step(), $timeout, $timeoutIdentity);
                if($timeoutReturn === $timeoutIdentity) {
                    $this->getLogger()->error("Step timed out");
                    Server::getInstance()->shutdown();
                    return;
                }

                $i++;
            }

            $this->getLogger()->notice("Finished all steps");

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
