<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

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
    ) {}

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
     * Returns the keys, including comment keys, of the current section.
     *
     * @return list<string>
     */
    public function getKeys() : array {
        $list = array_keys($this->data->get($this->path));

        foreach($list as &$key) {
            if(is_int($key)) {
                $key = (string) $key;
            } else {
                break;
            }
        }

        /** @var list<string> $list */
        return $list;
    }

    public function enter(string $key, string $doc) : Parser {
        $data = $this->expectAny($key, [], $doc, true, $path);

        if(!is_array($data) || self::isList($data)) {
            $data = $this->failSafe([],"Expected mapping for $key, got " . gettype($data));
            $this->data->set($path, $data);
        }

        return new self($this->data, $path, $this->failSafe);
    }

    /**
     * @throws ConfigException
     */
    public function expectInt(string $key, int $default, string $doc, bool $required = true) : int {
        $value = $this->expectAny($key, $default, $doc, $required, $path);

        if(!is_int($value)) {
            $value = $this->failSafe((int) $value, "Expected integer for $key, got " . gettype($value));
            $this->data->set($path, $value);
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectNumber(string $key, float $default, string $doc, bool $required = true) : float {
        $value = $this->expectAny($key, $default, $doc, $required, $path);

        if(!is_int($value) && !is_float($value)) {
            $value = $this->failSafe((float) $value, "Expected number for $key, got " . gettype($value));
            $this->data->set($path, $value);
        }

        return (float) $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectBool(string $key, bool $default, string $doc, bool $required = true) : bool {
        $value = $this->expectAny($key, $default, $doc, $required, $path);

        if(!is_bool($value)) {
            if(is_int($value) && ($value === 0 || $value === 1)) {
                $default = $value === 1;
            }

            $value = $this->failSafe($default, "Expected true/false for $key, got " . gettype($value));
            $this->data->set($path, $value);
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectString(string $key, string $default, string $doc, bool $required = true) : string {
        $value = $this->expectAny($key, $default, $doc, $required, $path);

        if(!is_string($value)) {
            $value = $this->failSafe((string) $value, "Expected string for $key, got " . gettype($value));
            $this->data->set($path, $value);
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public function expectNullableString(string $key, ?string $default, string $doc, bool $required = true) : ?string {
        $value = $this->expectAny($key, $default, $doc, $required, $path);

        if($value !== null && !is_string($value)) {
            $value = $this->failSafe((string) $value, "Expected null or string for $key, got " . gettype($value));
            $this->data->set($path, $value);
        }

        return $value;
    }

    /**
     * @param list<string> $default
     * @return list<string>
     * @throws ConfigException
     */
    public function expectStringList(string $key, array $default, string $doc, bool $required = true) : array {
        $value = $this->expectAny($key, $default, $doc, $required, $path);

        if(!is_array($value)) {
            $value = $this->failSafe([$value], "Expected $key to be a list, got " . gettype($value));
            $this->data->set($path, $value);
        }

        if(!self::isList($value)) {
            $value = $this->failSafe(array_values($value), "Expected $key to be a list, got mapping");
            $this->data->set($path, $value);
        }

        foreach($value as $i => &$v) {
            if(!is_string($v)) {
                $v = $this->failSafe((string) $v, "Expected $key to be a list of strings, but element #$i is " . gettype($v));
                $this->data->set($path, $value);
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
    public function expectNullableStringList(string $key, ?array $default, string $doc, bool $required = true) : ?array {
        $value = $this->expectAny($key, $default, $doc, $required, $path);

        if($value === null) {
            return null;
        }

        if(!is_array($value)) {
            $value = $this->failSafe([$value], "Expected $key to be a list, got " . gettype($value));
            $this->data->set($path, $value);
        }

        if(!self::isList($value)) {
            $value = $this->failSafe(array_values($value), "Expected $key to be a list, got mapping");
            $this->data->set($path, $value);
        }

        foreach($value as $i => &$v) {
            if(!is_string($v)) {
                $v = $this->failSafe((string) $v, "Expected $key to be a list of strings, but element #$i is " . gettype($v));
                $this->data->set($path, $value);
            }
        }
        unset($v);

        return $value;
    }

    /**
     * @template T
     * @param T $default
     * @param list<string> $fullPath
     * @return mixed
     */
    private function expectAny(string $key, $default, string $doc, bool $required, &$fullPath) {
        $array = $this->data->get($this->path);

        if(!array_key_exists($key, $array)) {
            if($this->failSafe || !$required) {
                $array["#" . $key] = $doc;
                $array[$key] = $default;
                $this->data->set($this->path, $array);
            } else {
                throw $this->throw("Missing attribute $key");
            }
        }

        $fullPath = array_merge($this->path, [$key]);

        return $array[$key];
    }

    /**
     * @template T
     * @param T $defaultValue
     * @return T
     */
    public function failSafe($defaultValue, string $exceptionMessage) {
        if($this->failSafe) {
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
