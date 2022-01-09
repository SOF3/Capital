<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use InvalidArgumentException;
use SOFe\Capital\AccountLabels;
use function count;

/**
 * The basic schema where each player only has one account.
 *
 * @implements Schema<BasicVars>
 */
final class Basic implements Schema {
    public function cloneWithConfig(array $config) : self {
        if(count($config) > 0) {
            throw new InvalidArgumentException("The basis schema does not support configuration");
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
