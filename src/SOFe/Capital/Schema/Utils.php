<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Generator;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\AccountRef;
use SOFe\Capital\Database\Database;

use function array_map;
use function array_merge;
use function array_shift;
use function array_slice;
use function count;
use function min;

final class Utils {
    /**
     * Parses command arguments (and removes them) and returns the label set for the schema.
     *
     * @param list<string> $args
     * @return Generator<mixed, mixed, mixed, Complete>
     * @throws InvalidArgumentException if the arguments cannot be inferred.
     */
    public static function fromCommand(Schema $schema, array &$args, CommandSender $sender) : Generator {
        $required = [];
        foreach ($schema->getRequiredVariables() as $var) {
            $required[] = $var;
        }

        $optional = [];
        foreach ($schema->getOptionalVariables() as $var) {
            $optional[] = $var;
        }

        if (count($args) < count($required)) {
            false && yield;
            // TODO try forms UI

            throw new InvalidArgumentException("Usage: " . self::getUsage($schema));
        }

        $clone = $schema->clone();

        foreach (array_merge($required, $optional) as $variable) {
            if (count($args) === 0) {
                break;
            }

            $value = array_shift($args);

            try {
                $variable->processValue($value, $clone);
            } catch (InvalidArgumentException $ex) {
                throw new InvalidArgumentException("Invalid value for {$variable->name}: {$ex->getMessage()}");
            }
        }

        return new Complete($clone);
    }

    /**
     * Generates a command usage string for the given schema.
     */
    public static function getUsage(Schema $schema) : string {
        $required = $schema->getRequiredVariables();
        $optional = $schema->getOptionalVariables();

        $usage = "Usage: ";

        foreach ($required as $variable) {
            $usage .= "<" . $variable->name . "> ";
        }

        foreach ($optional as $variable) {
            $usage .= "[" . $variable->name . "] ";
        }

        return $usage;
    }

    /**
     * Loads, create or migrate accounts for a parsed schema.
     *
     * @return Generator<mixed, mixed, mixed, list<AccountRef>>
     */
    public static function lazyCreate(Complete $schema, Database $db, Player $player) : Generator {
        $accounts = yield from $db->findAccounts($schema->getSelector($player));
        if (count($accounts) === 0) {
            $migrated = false;

            $migration = $schema->getMigrationSetup($player);
            if ($migration !== null) {
                $accounts = yield from $db->findAccounts($migration->migrationSelector);
                if (count($accounts) > 0) {
                    $updates = [];

                    foreach (array_slice($accounts, 0, min($migration->migrationLimit, count($accounts))) as $account) {
                        foreach ($migration->postMigrateLabels->getEntries() as $labelName => $labelValue) {
                            $updates[] = $db->accountLabels()->set($account, $labelName, $labelValue);
                        }
                    }

                    yield from Await::all($updates);

                    $migrated = true;
                }
            }

            if (!$migrated) {
                $initial = $schema->getInitialSetup($player);
                $account = yield from $db->createAccount($initial->initialValue, $initial->initialLabels->getEntries());
                $accounts = [$account];
            }
        }

        $touches = [];
        foreach ($accounts as $account) {
            $touches[] = $db->touchAccount($account);
            foreach ($schema->getOverwriteLabels($player)->getEntries() as $labelName => $labelValue) {
                $touches[] = $db->accountLabels()->set($account, $labelName, $labelValue);
            }
        }

        yield from Await::all($touches);

        return array_map(fn($id) => new AccountRef($id), $accounts);
    }
}
