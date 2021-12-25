<?php

declare(strict_types=1);

namespace SOFe\Capital;

use SOFe\InfoAPI\Info;
use SOFe\InfoAPI\InfoAPI;

/**
 * A parameterized label selector,
 * which can be transformed into a label selector given an instance of `I`.
 *
 * @template I of Info
 */
final class ParameterizedLabelSelector {
    /**
     * @param array<string, string> $entries
     */
    public function __construct(
        private array $entries,
        private bool $cache = true,
    ) {}

    public function transform(Info $info) : LabelSelector {
        $labels = [];
        foreach($this->entries as $name => $valueTemplate) {
            $labels[$name] = InfoAPI::resolve($valueTemplate, $info, $this->cache);
        }
        return new LabelSelector($labels);
    }
}
