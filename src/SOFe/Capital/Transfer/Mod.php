<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\ModInterface;
use SOFe\Capital\MainClass;
use SOFe\Capital\OracleNames;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(Context $context) : Generator {
        false && yield;

        ContextInfo::init();
        SuccessContextInfo::init();

        $context->call(function(Config $config, MainClass $plugin) {
            foreach($config->transferMethods as $method) {
                $method->register($plugin);
            }
        });

        Await::g2c(Capital::getOracle(OracleNames::TRANSFER));
    }

    public static function shutdown(Context $context) : void {}
}
