<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

/**
 * Settings related to players as account owners.
 */
final class Config {
    /**
     * @param Schema<object> $schema The default label schema.
     */
    public function __construct(
        public Schema $schema,
    ) {}
}
