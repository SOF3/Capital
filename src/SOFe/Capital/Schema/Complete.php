<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use AssertionError;
use pocketmine\player\Player;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\LabelSet;

/**
 * A complete schema is a schema that does not allow further configuration.
 */
final class Complete {
    public function __construct(private Schema $schema) {
        if (!$schema->isComplete()) {
            throw new AssertionError("Schema is not complete");
        }
    }

    /**
     * Returns the parameterized label selector with the given settings.
     *
     * This method returns null if and only if `isComplete()` returns false.
     *
     * It is guaranteed that one of the entries is `[ AccountLabels::PLAYER_UUID => $player->getUniqueId()->toString() ]`
     */
    public function getSelector(Player $player) : LabelSelector {
        $selector = $this->schema->getSelector($player);
        if ($selector === null) {
            throw new AssertionError("getSelector must not return null for complete schemas");
        }
        return $selector;
    }

    /**
     * Returns the labels to be overwritten every time an account is loaded.
     *
     * This is used for modifying existing configuration,
     * e.g. setting minimum and maximum values of accounts.
     */
    public function getOverwriteLabels(Player $player) : LabelSet {
        $overwrite = $this->schema->getOverwriteLabels($player);
        if ($overwrite === null) {
            throw new AssertionError("getOverwriteLabels must not return null for complete schemas");
        }
        return $overwrite;
    }

    /**
     * Returns the account migration settings.
     * Returns null if the schema is configured such that migration is not supported.
     */
    public function getMigrationSetup(Player $player) : ?MigrationSetup {
        return $this->schema->getMigrationSetup($player);
    }

    /**
     * Returns the initial accounts to create for this schema.
     */
    public function getInitialSetup(Player $player) : InitialSetup {
        $initial = $this->schema->getInitialSetup($player);
        if ($initial === null) {
            throw new AssertionError("getInitialSetup must not return null for complete schemas");
        }
        return $initial;
    }
}
