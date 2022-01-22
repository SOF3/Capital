<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\InfoAPI\Info;

/**
 * An initial account for a player.
 *
 * @template I of Info
 */
final class MigrationSetup {
    /**
     * @param ParameterizedLabelSelector<I> $migrationSelector Labels used to search for fallback accounts for migration purposes.
     * @param ParameterizedLabelSet<I> $postMigrateLabels New labels applied on migrated accounts.
     * @param int $migrationLimit The maximum number of accounts to migrate each time. Accounts are selected randomly if there are more accounts than the migration limit.
     */
    public function __construct(
        public ParameterizedLabelSelector $migrationSelector,
        public ParameterizedLabelSet $postMigrateLabels,
        public int $migrationLimit,
    ) {}
}
