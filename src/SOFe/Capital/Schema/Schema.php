<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use InvalidArgumentException;

/**
 * `Schema` defines the common player account labels and how to generate them.
 *
 * This provides a simpler abstraction for other plugins to handle configuration
 * without exposing the raw concept of labels to users directly.
 *
 * @template V of object The class storing variables.
 */
interface Schema {
    /**
     * Constructs the schema from config.
     *
     * @param array<string, mixed> $globalConfig
     * @return Schema<V>
     */
    public static function build(array $globalConfig) : self;

    /**
     * Constructs the schema from config.
     *
     * @param array<string, mixed> $inferConfig The malformed config to build on.
     * @return Schema<V>
     */
    public static function infer(array $inferConfig) : self;

    /**
     * Generates the config for this instance.
     *
     * @return array<string, mixed>
     */
    public function getConfig() : array;

    /**
     * Clones this schema with specific config values.
     *
     * @param array<string, mixed> $specificConfig
     * @return Schema<V> A new object that is **not** `$this` (must be a different object even if config is empty)
     * @throws InvalidArgumentException if the config is invalid.
     */
    public function cloneWithConfig(array $specificConfig) : self;

    /**
     * Returns the required variables used in this label set.
     *
     * The list of values may depend on the config values,
     * e.g. if config did not specify some value, it can be added to the list of required variables.
     *
     * @return iterable<SchemaVariable<V, mixed>>
     */
    public function getRequiredVariables() : iterable;

    /**
     * Returns the optional variables used in this label set.
     *
     * @return iterable<SchemaVariable<V, mixed>>
     */
    public function getOptionalVariables() : iterable;

    /**
     * Creates a new instance of `V`, with default values set.
     *
     * It is allowed that some required fields remain uninitialized,
     * as long as they are initialized in populators in `getRequiredVariables`.
     *
     * @return V
     */
    public function newV() : object;

    /**
     * Returns the (parameterized) label set/selector, including global labels set by the config.
     *
     * @param V $v
     * @return array<string, string>
     */
    public function vToLabels($v, string $playerPath) : array;
}
