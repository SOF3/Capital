<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use function implode;
use function in_array;

/**
 * A schema variable is a scalar specified by users when interacting with Capital
 * to select user accounts.
 *
 * @template S of Schema The schema to populate.
 * @template T The type that the variable actually uses.
 */
final class Variable {
    /** A string variable */
    public const TYPE_STRING = "string";
    /** An integer variable */
    public const TYPE_INT = "int";
    /** A float variable */
    public const TYPE_FLOAT = "float";
    /** A boolean variable */
    public const TYPE_BOOL = "bool";

    /**
     * @param Variable::TYPE_* $type The type of the variable.
     * @param string $name A short, human-readable description of the variable, used in command usage and CustomForm component names.
     * @param Closure(S, T): void $populate A function that populates the schema with the given value.
     * @param null|Closure(mixed): T $transform A function to transform the raw response into the type that the variable uses. The input may be command argument string or form response entry. The output is used in `$populate` and `$validate`. Throws an `InvalidArgumentException` if the input is invalid. The exception message will be sent to the player.
     * @param null|Closure(T): void $validate A function to validate the transformed response. The input is the transformed response. Throws an `InvalidArgumentException` if the input is invalid. The exception message will be sent to the player.
     * @param null|list<string> $enumValues A list of possible string values. Only allowed if `$type` is `Variable::TYPE_STRING`. This is executed before `$validate`.
     * @param null|array{int, int}|array{float, float} $range The range of the variable. Only allowed if `$type` is `Variable::TYPE_INT` or `Variable::TYPE_FLOAT`. This is executed before `$validate`.
     * @param null|T $default The default value of the variable.
     */
    public function __construct(
        public string $type,
        public string $name,
        public Closure $populate,
        public ?Closure $transform = null,
        public ?Closure $validate = null,
        public ?array $enumValues = null,
        public ?array $range = null,
        public $default = null,
    ) {
        if ($enumValues !== null && $type !== self::TYPE_STRING) {
            throw new RuntimeException("Enum values are ignored if the type is not string");
        }
        if ($range !== null && $type !== self::TYPE_INT && $type !== self::TYPE_FLOAT) {
            throw new RuntimeException("Range is ignored if the type is not int or float");
        }
    }

    /**
     * @param S $schema
     * @throws InvalidArgumentException
     */
    public function processValue(mixed $value, Schema $schema) : void {
        if ($this->transform !== null) {
            $value = ($this->transform)($value);
        }

        if ($this->type === self::TYPE_STRING && $this->enumValues !== null) {
            if (!in_array($value, $this->enumValues, true)) {
                throw new InvalidArgumentException("expected one of \"" . implode("\", \"", $this->enumValues) . "\"");
            }
        }

        if ($this->type === self::TYPE_INT || $this->type === self::TYPE_FLOAT) {
            if ($this->range !== null) {
                [$min, $max] = $this->range;

                if ($value < $min || $value > $max) {
                    throw new InvalidArgumentException("Invalid value for $this->name, expected a value between $min and $max");
                }
            }
        }

        if ($this->validate !== null) {
            ($this->validate)($value);
        }

        ($this->populate)($schema, $value);
    }
}
