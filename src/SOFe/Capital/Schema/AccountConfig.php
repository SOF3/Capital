<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Closure;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\InfoAPI\Info;

/**
 * The configuration in a schema that describes a single account.
 */
final class AccountConfig {
    public static function parse(Parser $parser) : self {
        $initialBalance = $parser->expectInt("default", 0, "Default amount of money when the account is created");

        $min = $parser->expectInt("min", 0, <<<'EOT'
            The minimum amount of money in this account.
            If set to negative, this account can have a negatie balance (i.e. overdraft).
            EOT);
        if($initialBalance < $min) {
            $initialBalance = $parser->failSafe($min, "default balance is smaller than minimum ($min)");
        }

        $max = $parser->expectInt("max", 100_000_000, <<<'EOT'
            The maximum amount of money in this account.
            If this value has more than 10 digits, it may cause problems in some platforms.
            EOT);

        if($initialBalance > $max) {
            $initialBalance = $parser->failSafe($max, "default balance is greater than maximum ($max)");
        }

        $importFrom = $parser->expectNullableString("import-from", "economyapi", <<<'EOT'
            Accounts from the specified sources will be converted to this account type.

            Enabling this option does NOT import the database from other databases.
            Please use the migration tool to import the database first,
            then enable this option to start importing accounts for new players.

            This option does NOT affect players who already have an account of this type.

            Possible values: ~ (do not import), economyapi
            EOT);

        $migration = null;
        if($importFrom !== null) {
            $migration = function(string $playerPath) use($importFrom) : MigrationSetup {
                $migrationSelector = new ParameterizedLabelSelector([
                    AccountLabels::PLAYER_NAME => "\{$playerPath name}",
                    AccountLabels::MIGRATION_SOURCE => $importFrom,
                ]);
                $postMigrateLabels = new ParameterizedLabelSet([]);
                $migrationLimit = 1;

                return new MigrationSetup($migrationSelector, $postMigrateLabels, $migrationLimit);
            };
        }

        return new self(
            overwriteLabels: fn(string $playerPath) => new ParameterizedLabelSet([
                AccountLabels::PLAYER_NAME => "{{$playerPath} name}",
            ]),
            migrationSetup: $migration,
            initialLabels: fn(string $playerPath) => new ParameterizedLabelSet([
                AccountLabels::VALUE_MIN => (string) $min,
                AccountLabels::VALUE_MAX => (string) $max,
            ]),
            initialBalance: $initialBalance,
        );
    }

    /**
     * @param Closure(string): ParameterizedLabelSet<Info> $overwriteLabels A function that returns the overwrite label set parameterized by the given player path.
     * @param null|Closure(string): MigrationSetup<Info> $migrationSetup A function that returns the migration setup parameterized by the given player path.
     * @param Closure(string): ParameterizedLabelSet<Info> $initialLabels A function that returns the initial label set parameterized by the given player path.
     * @param int $initialBalance The initial balance.
     */
    public function __construct(
        private Closure $overwriteLabels,
        private ?Closure $migrationSetup,
        private Closure $initialLabels,
        private int $initialBalance,
    ) {}

    /**
     * @return ParameterizedLabelSet<Info>
     */
    public function getOverwriteLabels(string $playerPath) : ParameterizedLabelSet {
        return ($this->overwriteLabels)($playerPath);
    }

    /**
     * @return ?MigrationSetup<Info>
     */
    public function getMigrationSetup(string $playerPath) : ?MigrationSetup {
        return $this->migrationSetup !== null ? ($this->migrationSetup)($playerPath) : null;
    }

    /**
     * @return InitialSetup<Info>
     */
    public function getInitialSetup(string $playerPath) : InitialSetup {
        return new InitialSetup(
            ($this->initialLabels)($playerPath),
            $this->initialBalance,
        );
    }
}
