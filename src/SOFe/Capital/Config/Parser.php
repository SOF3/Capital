<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Closure;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_values;
use function count;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function range;

final class Parser {
    /**
     * @param ArrayRef $data Raw config data.
     * @param list<string> $path The key path to the data.
     * @param bool $failSafe If true, the config is parsed in recovery mode,
     *                       and the plugin should try to regenerate the config with correct data.
     */
    public function __construct(
        private ArrayRef $data,
        private array $path,
        private bool $failSafe,
    ) {
    }

    public function isFailSafe() : bool {
        return $this->failSafe;
    }

    /**
     * Returns the full config array (not just this parser).
     *
     * @return array<string, mixed>
     */
    public function getFullConfig() : array {
        return $this->data->array;
    }

    /**
     * Returns the keys of the current section, excluding comment keys.
     *
     * @return list<string>
     */
    public function getKeys() : array {
        $list = array_keys($this->data->get($this->path));

        $output = [];
        foreach ($list as $key) {
            if (is_int($key)) {
                $key = (string) $key;
            }

            if ($key[0] === "#") {
                continue;
            }

            $output[] = $key;
        }

        return $output;
    }

    /**
     * @param bool $isNew
     */
    public function enter(string $key, ?string $doc, &$isNew = false) : Parser {
        $data = $this->expectAny($key, [], $doc, true, $isNew);

        $isNew = $isNew || self::isList($data);

        if ($isNew) {
            $data = $this->setValue($key, [], "Expected mapping, got " . gettype($data));
        }

        return new self($this->data, array_merge($this->path, [$key]), $this->failSafe);
    }

    /**
     * @throws ConfigException
     */
    public function expectInt(string $key, int $default, ?string $doc, bool $required = true) : int {
        $value = $this->expectAny($key, $default, $doc, $required);

        if (!is_int($value)) {
            $value = $this->setValue($key, (int) $value, "Expected integer, got " . gettype($value));
            ;
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectNullableInt(string $key, ?int $default, ?string $doc, bool $required = true) : ?int {
        $value = $this->expectAny($key, $default, $doc, $required);

        if ($value !== null && !is_int($value)) {
            $value = $this->setValue($key, (int) $value, "Expected integer, got " . gettype($value));
            ;
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectNumber(string $key, float $default, ?string $doc, bool $required = true) : float {
        $value = $this->expectAny($key, $default, $doc, $required);

        if (!is_int($value) && !is_float($value)) {
            $value = $this->setValue($key, (float) $value, "Expected number, got " . gettype($value));
        }

        return (float) $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectNullableNumber(string $key, ?float $default, ?string $doc, bool $required = true) : ?float {
        $value = $this->expectAny($key, $default, $doc, $required);

        if ($value !== null && !is_int($value) && !is_float($value)) {
            $value = $this->setValue($key, (float) $value, "Expected number, got " . gettype($value));
        }

        if (is_int($value)) {
            $value = (float) $value;
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectBool(string $key, bool $default, ?string $doc, bool $required = true) : bool {
        $value = $this->expectAny($key, $default, $doc, $required);

        if (!is_bool($value)) {
            if (is_int($value) && ($value === 0 || $value === 1)) {
                $default = $value === 1;
            }

            $value = $this->setValue($key, $default, "Expected true/false, got " . gettype($value));
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectString(string $key, string $default, ?string $doc, bool $required = true) : string {
        $value = $this->expectAny($key, $default, $doc, $required);

        if (!is_string($value)) {
            $value = $this->setValue($key, (string) $value, "Expected string, got " . gettype($value));
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectNullableString(string $key, ?string $default, ?string $doc, bool $required = true) : ?string {
        $value = $this->expectAny($key, $default, $doc, $required);

        if ($value !== null && !is_string($value)) {
            $value = $this->setValue($key, (string) $value, "Expected null or string, got " . gettype($value));
        }

        return $value;
    }

    /**
     * @param list<string> $default
     * @return list<string>
     * @throws ConfigException
     */
    public function expectStringList(string $key, array $default, ?string $doc, bool $required = true) : array {
        $value = $this->expectAny($key, $default, $doc, $required);

        if (!is_array($value)) {
            $value = $this->setValue($key, [$value], "Expected a list, got " . gettype($value));
        }

        if (!self::isList($value)) {
            $value = $this->setValue($key, array_values($value), "Expected a list, got mapping");
        }

        foreach ($value as $i => &$v) {
            if (!is_string($v)) {
                $v = (string) $v;
                $this->setValue($key, $value, "Expected a list of strings, but element #$i is " . gettype($value));
            }
        }
        unset($v);

        return $value;
    }

    /**
     * @param list<string>|null $default
     * @return list<string>|null
     * @throws ConfigException
     */
    public function expectNullableStringList(string $key, ?array $default, ?string $doc, bool $required = true) : ?array {
        $value = $this->expectAny($key, $default, $doc, $required);

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            $value = $this->setValue($key, [$value], "Expected a list, got " . gettype($value));
        }

        if (!self::isList($value)) {
            $value = $this->setValue($key, array_values($value), "Expected a list, got mapping");
        }

        foreach ($value as $i => &$v) {
            if (!is_string($v)) {
                $v = (string) $v;
                $this->setValue($key, $value, "Expected a list of strings, but element #$i is " . gettype($value));
            }
        }
        unset($v);

        return $value;
    }

    /**
     * @param list<array<string, mixed>> $default
     * @return list<Parser>
     * @throws ConfigException
     */
    public function expectObjectList(string $key, array $default, ?string $doc, bool $required = true) : ?array {
        $value = $this->expectAny($key, $default, $doc, $required);

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            $value = $this->setValue($key, $default, "Expected a list, got " . gettype($value));
        }

        if (!self::isList($value)) {
            $value = $this->setValue($key, [$value], "Expected a list, got mapping");
        }

        $result = [];
        foreach ($value as $index => $mapping) {
            if (!is_array($mapping) || self::isList($mapping)) {
                $this->setValue($key, $value, "Expected [{$index}] to be a mapping, got " . gettype($value));
            }

            $element = new self($this->data, array_merge($this->path, [$key, $index]), $this->failSafe);
            $result[] = $element;
        }
        return $result;
    }

    /**
     * @template T
     * @param T $default
     * @param bool $isNew
     * @return mixed
     */
    private function expectAny(string $key, $default, ?string $doc, bool $required, &$isNew = false) {
        $array = $this->data->get($this->path);

        $isNew = !array_key_exists($key, $array);
        if ($isNew) {
            if ($this->failSafe || !$required) {
                if ($doc !== null) {
                    $array["#" . $key] = $doc;
                }
                $array[$key] = $default;
                $this->data->set($this->path, $array);
            } else {
                throw $this->throw("Missing attribute $key");
            }
        }

        return $array[$key];
    }

    /**
     * @template T
     * @param T $value
     * @return T
     */
    public function setValue(string $key, $value, string $message) {
        $this->failSafe(null, "Invalid $key: $message");
        $this->data->set(array_merge($this->path, [$key]), $value);
        return $value;
    }

    /**
     * @template T
     * @template R
     * @param T $value
     * @param Closure(): R $return
     * @return R
     */
    public function setValueAnd(string $key, $value, string $message, Closure $return) {
        $this->failSafe(null, "Invalid $key: $message");
        $this->data->set(array_merge($this->path, [$key]), $value);
        return $return();
    }

    /**
     * @template T
     * @param T $defaultValue
     * @return T
     */
    public function failSafe($defaultValue, string $exceptionMessage) {
        if ($this->failSafe) {
            return $defaultValue;
        } else {
            throw $this->throw($exceptionMessage);
        }
    }

    private function throw(string $message) : ConfigException {
        throw new ConfigException("Error at " . implode(".", $this->path) . ": " . $message);
    }

    /**
     * @param array<mixed, mixed> $array
     */
    private static function isList(array $array) : bool {
        return array_keys($array) === range(0, count($array) - 1);
    }
}
