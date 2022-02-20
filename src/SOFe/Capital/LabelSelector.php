<?php

declare(strict_types=1);

namespace SOFe\Capital;

use RuntimeException;

use function assert;
use function implode;
use function ksort;
use function strlen;
use function strpos;
use function substr;

/**
 * Selects accounts or transactions by label values.
 * If the label value is empty (`ANY_VALUE`), the selector matches as long as the label exists.
 *
 * An empty label selector matches all accounts or transactions.
 */
final class LabelSelector {
    /** A special constant string that matches all labels. */
    public const ANY_VALUE = "";

    /**
     * @param array<string, string> $entries
     */
    public function __construct(
        private array $entries,
    ) {
        ksort($this->entries);
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

    public function without(string $key) : LabelSelector {
        $entries = $this->entries;
        if(!isset($entries[$key])) {
            throw new RuntimeException("$key is not in the label selector");
        }

        unset($entries[$key]);
        return new LabelSelector($entries);
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
