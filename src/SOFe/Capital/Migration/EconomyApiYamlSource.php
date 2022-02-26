<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

final class EconomyApiYamlSource implements Source {
    public function __construct(private string $path) {
    }
}
