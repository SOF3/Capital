<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use RuntimeException;
use function array_slice;
use function count;
use function gettype;
use function implode;
use function is_array;

final class ArrayRef {
    /**
     * @param array<string, mixed> $array
     */
    public function __construct(
        public array $array,
    ) {}

    /**
     * @param list<string> $path
     */
    public function get(array $path) : mixed {
        $current = $this->array;

        foreach($path as $key) {
            if(!isset($current[$key])) {
                throw new RuntimeException("Invalid path " . implode(".", $path));
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * @param list<string> $path
     */
    public function set(array $path, mixed $value) : void {
        if(count($path) === 0) {
            if(!is_array($value)) {
                throw new RuntimeException("Cannot set the whole array to " . gettype($value));
            }

            $this->array = $value;
            return;
        }

        $current = &$this->array;

        foreach(array_slice($path, 0, -1) as $key) {
            if(!isset($current[$key])) {
                throw new RuntimeException("Invalid path " . implode(".", $path));
            }
            $current = &$current[$key];
        }

        $current[$path[count($path) - 1]] = $value;
    }
}
