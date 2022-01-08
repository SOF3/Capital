<?php

declare(strict_types=1);

namespace SOFe\Capital;

use InvalidArgumentException;
use pocketmine\command\CommandSender;
use function array_merge;
use function array_shift;
use function count;

final class SchemaUtils {
    /**
     * Parses command arguments (and removes them) and returns the label set for the schema.
     *
     * @template V of object
     * @param Schema<V> $schema
     * @param list<string> $args
     * @return null|array<string, string>
     */
    public static function fromCommand(Schema $schema, array &$args, CommandSender $sender, string $playerPath) : ?array {
        $required = [];
        foreach($schema->getRequiredVariables() as $var) {
            $required[] = $var;
        }

        $optional = [];
        foreach($schema->getOptionalVariables() as $var) {
            $optional[] = $var;
        }

        if(count($args) < count($required)) {
            // TODO try forms UI

            throw new InvalidArgumentException("Usage: " . self::getUsage($schema));
        }

        $v = $schema->newV();

        foreach(array_merge($required, $optional) as $variable) {
            if(count($args) === 0) {
                break;
            }

            $value = array_shift($args);

            try {
                $variable->processValue($value, $v);
            } catch(InvalidArgumentException $ex) {
                throw new InvalidArgumentException("Invalid value for {$variable->name}: {$ex->getMessage()}");
            }
        }

        return $schema->vToLabels($v, $playerPath);
    }

    /**
     * Generates a command usage string for the given schema.
     *
     * @template V of object
     * @param Schema<V> $schema
     */
    public static function getUsage(Schema $schema) : string {
        $required = $schema->getRequiredVariables();
        $optional = $schema->getOptionalVariables();

        $usage = "Usage: ";

        foreach($required as $variable) {
            $usage .= "<" . $variable->name . "> ";
        }

        foreach($optional as $variable) {
            $usage .= "[" . $variable->name . "] ";
        }

        return $usage;
    }
}
