<?php

declare(strict_types=1);

namespace SOFe\Capital;

use SOFe\Capital\Config\Parser;
use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;
use function count;

/**
 * A parameterized label set,
 * which can be transformed into a label set given an instance of `I`.
 *
 * @template I of Info
 */
final class ParameterizedLabelSet {
    /**
     * @param array<string, string> $entries
     */
    public function __construct(
        private array $entries,
        private bool $cache = true,
    ) {}

    /**
     * @return array<string, string>
     */
    public function transform(Info $info) : array {
        $labels = [];
        foreach($this->entries as $name => $valueTemplate) {
            $labels[$name] = InfoAPI::resolve($valueTemplate, $info, $this->cache);
        }
        return $labels;
    }

    /**
     * @template T of Info
     * @param array<string, string> $defaultEntries
     * @return ParameterizedLabelSet<T>
     */
    public static function parse(Parser $parser, $defaultEntries = []) : self
    {
        $labelNames = $parser->getKeys();
        if (count($labelNames) === 0) {
            $entries = $defaultEntries;
            foreach ($defaultEntries as $name => $value) {
                $parser->setValue($name, $value, "");
            }
        } else {
            $entries = [];
            foreach ($labelNames as $name) {
                $entries[$name] = $parser->expectString($name, "", "");
            }
        }

        /** @var self $labelSet */
        $labelSet = new self($entries);
        return $labelSet;
    }
}
