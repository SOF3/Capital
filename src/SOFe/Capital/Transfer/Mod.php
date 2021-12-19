<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\Config\Config;
use SOFe\Capital\MainClass;
use SOFe\Capital\ModInterface;
use SOFe\Capital\OracleNames;
use SOFe\Capital\TypeMap;

final class Mod implements ModInterface {
    public const API_VERSION = "0.1.0";

    /**
     * @return VoidPromise
     */
    public static function init(TypeMap $typeMap) : Generator {
        false && yield;

        TransferContextInfo::init();
        TransferSuccessContextInfo::init();

        $config = Config::get($typeMap);
        $plugin = MainClass::get($typeMap);

        foreach($config->transfer->transferMethods as $method) {
            $method->register($plugin);
        }

        Await::g2c(Capital::getOracle(OracleNames::TRANSFER));
    }

    public static function shutdown(TypeMap $typeMap) : void {}
}
