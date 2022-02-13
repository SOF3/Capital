<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use pocketmine\player\Player;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\LabelSet;

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
    ) {
    }

    public static function describe() : string {
        return "Each player only has one account.";
    }

    public function clone() : self {
        return clone $this;
    }

    public function cloneWithConfig(Parser $specificConfig) : self {
        return clone $this;
    }

    public function cloneWithCompleteConfig(Parser $specificConfig) : Complete {
        return new Complete(clone $this);
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

    public function getSelector(Player $player) : ?LabelSelector {
        return new LabelSelector([
            AccountLabels::PLAYER_UUID => $player->getUniqueId()->toString(),
        ]);
    }

    public function getOverwriteLabels(Player $player) : ?LabelSet {
        return $this->initialAccount->getOverwriteLabels($player);
    }

    public function getMigrationSetup(Player $player) : ?MigrationSetup {
        return $this->initialAccount->getMigrationSetup($player);
    }

    public function getInitialSetup(Player $player) : ?InitialSetup {
        return $this->initialAccount->getInitialSetup($player);
    }
}
