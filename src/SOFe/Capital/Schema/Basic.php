<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\ParameterizedLabelSet;

/**
 * The basic schema where each player only has one account.
 */
final class Basic implements Schema {
    public static function build(Parser $globalConfig) : self {
        $initialAccount = AccountConfig::parse($globalConfig->enter("account", <<<'EOT'
            The initial account setup.
            EOT));
        return new self($initialAccount);
    }

    public function __construct(
        private AccountConfig $initialAccount,
    ) {}

    public static function describe() : string {
        return "Each player only has one account.";
    }

    public function cloneWithConfig(?Parser $specificConfig) : self {
        return clone $this;
    }

    public function isComplete() : bool {
        return true;
    }

    public function getRequiredVariables() : iterable {
        return [];
    }

    public function getOptionalVariables() : iterable {
        return [];
    }

    public function getSelector(string $playerPath) : ?ParameterizedLabelSelector {
        return new ParameterizedLabelSelector([
            AccountLabels::PLAYER_UUID => "{{$playerPath} uuid}",
        ]);
    }

    public function getOverwriteLabels(string $playerPath) : ?ParameterizedLabelSet {
        return $this->initialAccount->getOverwriteLabels($playerPath);
    }

    public function getMigrationSetup(string $playerPath) : ?MigrationSetup {
        return $this->initialAccount->getMigrationSetup($playerPath);
    }

    public function getInitialSetup(string $playerPath) : ?InitialSetup {
        return $this->initialAccount->getInitialSetup($playerPath);
    }
}
