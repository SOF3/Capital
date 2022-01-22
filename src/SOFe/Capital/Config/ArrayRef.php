<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use RuntimeException;
use function implode;

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
        $current = &$this->array;

        foreach($path as $key) {
            if(!isset($current[$key])) {
                throw new RuntimeException("Invalid path " . implode(".", $path));
            }
            $current = &$current[$key];
        }

        $current = $value;
    }
}
