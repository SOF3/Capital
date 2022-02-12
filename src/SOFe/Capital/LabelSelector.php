<?php

declare(strict_types=1);

namespace SOFe\Capital;

use function assert;
use function implode;
use function strlen;
use function strpos;
use function substr;

final class LabelSelector {
    /** A special constant string that matches all labels. */
    public const ANY_VALUE = "";

    /**
     * @param array<string, string> $entries
     */
    public function __construct(
        private array $entries,
    ) {
    }

    /**
     * Returns a unique bytestring representation of this selector.
     */
    public function toBytes() : string {
        $bytes = "";
        foreach ($this->entries as $key => $value) {
            $bytes .= $key . "\0" . $value . "\0";
        }
        return $bytes;
    }

    /**
     * @return array<string, string>
     */
    public function getEntries() : array {
        return $this->entries;
    }

    public static function parseEntries(string $bytes) : self {
        $index = 0;

        $output = [];
        while ($index < strlen($bytes)) {
            $pos = strpos($bytes, "\0", $index);
            assert($pos !== false);
            $key = substr($bytes, $index, $pos);
            $index = $pos + 1;

            $pos = strpos($bytes, "\0", $index);
            assert($pos !== false);
            $value = substr($bytes, $index, $pos);
            $index = $pos + 1;

            $output[$key] = $value;
        }

        return new self($output);
    }

    public function debugDisplay() : string {
        $output = [];
        foreach ($this->entries as $key => $value) {
            $output[] = $key . "=" . $value;
        }
        return implode(", ", $output);
    }
}
