<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use InvalidArgumentException;
use pocketmine\command\CommandSender;

use function array_merge;
use function array_shift;
use function count;

final class SchemaUtils {
    /**
     * Parses command arguments (and removes them) and returns the label set for the schema.
     *
     * @param list<string> $args
     */
    public static function fromCommand(Schema $schema, array &$args, CommandSender $sender, string $playerPath) : Schema {
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

        $clone = $schema->cloneWithConfig(null);

        foreach(array_merge($required, $optional) as $variable) {
            if(count($args) === 0) {
                break;
            }

            $value = array_shift($args);

            try {
                $variable->processValue($value, $clone);
            } catch(InvalidArgumentException $ex) {
                throw new InvalidArgumentException("Invalid value for {$variable->name}: {$ex->getMessage()}");
            }
        }

        return $schema;
    }

    /**
     * Generates a command usage string for the given schema.
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
