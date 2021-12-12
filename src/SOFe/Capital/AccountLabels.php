<?php

declare(strict_types=1);

namespace SOFe\Capital;

/**
 * Standard account labels used by Capital itself.
 */
final class AccountLabels {
    /** Associates the ownership of an account with a player. The value is the player's in-game name. */
    public const PLAYER_NAME = "capital/playerName";
    /** Associates the ownership of an account with a player. The value is the player's UUID. */
    public const PLAYER_UUID = "capital/playerUuid";
    /** Constrains the minimum value in the account. The value is expressed as signed decimal form. */
    public const VALUE_MIN = "capital/coreValueMin";
    /** Constrains the maximum value in the account. The value is expressed as signed decimal form. */
    public const VALUE_MAX = "capital/coreValueMax";

    /** Marks an account as a system account that must not be automatically deleted. */
    public const NO_DELETION = "capital/noDeletion";

    /** Identifies the account as a functional oracle specialized for an operation indicated in the value. */
    public const ORACLE = "capital/oracle";

    /** Expose the account as an InfoAPI subinfo for a player. The value is the info name prefixed by `capital.player.`. */
    public const PLAYER_INFO_NAME = "capital/playerInfoName";
}
