<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\ConfigException;

use function count;

/**
 * The basic schema where each player only has one account.
 *
 * @implements Schema<BasicVars>
 */
final class Basic implements Schema {
    public static function build(array $globalConfig) : self {
        return new self;
    }

    public static function infer(array $inferConfig) : self {
        return new self;
    }

    public function getConfig() : array {
        return [];
    }

    public function cloneWithConfig(array $specificConfig) : self {
        if(count($specificConfig) > 0) {
            throw new ConfigException("The basis schema does not support configuration");
        }

        return clone $this;
    }

    public function getRequiredVariables() : iterable {
        return [];
    }

    public function getOptionalVariables() : iterable {
        return [];
    }

    public function newV() : BasicVars {
        return new BasicVars;
    }

    public function vToLabels($v, string $playerPath) : array {
        return [
            AccountLabels::PLAYER_UUID => "{{$playerPath} uuid}",
        ];
    }
}
