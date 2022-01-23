<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\LabelSelector;
use SOFe\Capital\LabelSet;

/**
 * An initial account for a player.
 */
final class MigrationSetup {
    /**
     * @param LabelSelector $migrationSelector Labels used to search for fallback accounts for migration purposes.
     * @param LabelSet $postMigrateLabels New labels applied on migrated accounts.
     * @param int $migrationLimit The maximum number of accounts to migrate each time. Accounts are selected randomly if there are more accounts than the migration limit.
     */
    public function __construct(
        public LabelSelector $migrationSelector,
        public LabelSet $postMigrateLabels,
        public int $migrationLimit,
    ) {}
}
