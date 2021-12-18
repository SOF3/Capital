<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\Config;
use SOFe\Capital\ModInterface;
use SOFe\Capital\OracleNames;

final class Mod implements ModInterface {
    public static function init() : void {
        foreach(Config::getInstance()->transfer->transferMethods as $method) {
            $method->register();
        }

        Await::g2c(Capital::getOracle(OracleNames::TRANSFER));
    }

    public static function shutdown() : void {}
}
