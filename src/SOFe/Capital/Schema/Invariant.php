<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use AssertionError;
use pocketmine\player\Player;
use SOFe\Capital\LabelSelector;

/**
 * An invariant schema is a schema that does not depend on player information other than the UUID to locate an account.
 * This is similar to complete schemas, but disallows schemas like `World` from reading real-time player states such as location,
 * and requires these information to be specified by the config in advance.
 */
final class Invariant {
    public function __construct(private Schema $schema) {
        if (!$schema->isInvariant()) {
            throw new AssertionError("Schema is not invariant");
        }
        if (!$schema->isComplete()) {
            throw new AssertionError("Schema is not complete but invariance implies completeneess");
        }
    }

    public function asComplete() : Complete {
        return new Complete($this->schema);
    }

    /**
     * Returns the parameterized label selector with the given settings.
     *
     * This method returns null if and only if `isComplete()` returns false.
     */
    public function getSelector() : LabelSelector {
        $selector = $this->schema->getInvariantSelector();
        if ($selector === null) {
            throw new AssertionError("getSelector must not return null for invariant schemas");
        }
        return $selector;
    }
}
