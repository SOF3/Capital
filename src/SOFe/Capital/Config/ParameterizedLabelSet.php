<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;

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
}
