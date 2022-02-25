<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use pocketmine\player\Player;
use SOFe\Capital\Config\ConfigException;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\LabelSet;

/**
 * `Schema` defines the common player account labels and how to generate them.
 *
 * This provides a simpler abstraction for other plugins to handle configuration
 * without exposing the raw concept of labels to users directly.
 */
interface Schema {
    /**
     * Constructs the schema from config.
     */
    public static function build(Parser $globalConfig) : self;

    /**
     * Returns the config description of the schema type.
     */
    public static function describe() : string;

    /**
     * Clones this schema (e.g. for populating config variables).
     */
    public function clone() : self;

    /**
     * Clones this schema with specific config values.
     *
     * @throws ConfigException if the config is invalid.
     */
    public function cloneWithConfig(Parser $specificConfig) : self;

    /**
     * Clones this schema with specific config values, expecting a complete config.
     *
     * @throws ConfigException if the config is invalid, including incompleteness.
     */
    public function cloneWithCompleteConfig(Parser $specificConfig) : Complete;

    /**
     * Clones this schema with specific config values, expecting an invariant config.
     *
     * @throws ConfigException if the config is invalid, such as lacking invariance.
     */
    public function cloneWithInvariantConfig(Parser $specificConfig) : Invariant;

    /**
     * Returns whether all required variables have been populated.
     */
    public function isComplete() : bool;

    /**
     * Returns whether the selector can be generated without reading player information other than the UUID.
     *
     * Invariance implies completeness, but a complete schema is not necessarily invariant.
     */
    public function isInvariant() : bool;

    /**
     * Returns the required variables used in this label set.
     *
     * The list of values depends on the config values.
     * Variables are required only if they are not already set.
     *
     * `isComplete` returns true if and only if this method returns an empty iterator.
     *
     * After all variables returned by this method
     * have been called [`Variable::processValue`] without throwing exceptions,
     * this schema object is mutated such that `isComplete` must return true.
     *
     * @return iterable<Variable<static, mixed>>
     */
    public function getRequiredVariables() : iterable;

    /**
     * Returns the optional variables used in this label set.
     *
     * The list of values depends on the config values.
     * Variables are moved from required to optional after they have been set.
     *
     * @return iterable<Variable<static, mixed>>
     */
    public function getOptionalVariables() : iterable;

    /**
     * Returns the label selector with the given settings,
     * or null if the required variables have not all been set.
     *
     * This method returns null if and only if `isComplete()` returns false.
     */
    public function getSelector(Player $player) : ?LabelSelector;

    /**
     * Returns the label selector that, for each player,
     * when combined with `AccountLabels::PLAYER_UUID => $uuid`,
     * is equivalent to `getSelector`.
     * Returns null if and only if `isInvariant()` returns false.
     */
    public function getInvariantSelector() : ?LabelSelector;

    /**
     * Returns the labels to be overwritten every time an account is loaded.
     * Reteurns null if and only if `isComplete()` is false.
     *
     * This is used for modifying existing configuration,
     * e.g. setting minimum and maximum values of accounts.
     */
    public function getOverwriteLabels(Player $player) : ?LabelSet;

    /**
     * Returns the account migration settings.
     * Returns null if `isComplete()` is false OR
     * if the schema is configured such that migration is not supported.
     */
    public function getMigrationSetup(Player $player) : ?MigrationSetup;

    /**
     * Returns the initial accounts to create for this schema.
     * Returns null if and only if `isComplete()` is false.
     */
    public function getInitialSetup(Player $player) : ?InitialSetup;
}
