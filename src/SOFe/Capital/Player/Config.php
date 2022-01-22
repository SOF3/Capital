<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use SOFe\Capital\Config\Constants;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Schema;

/**
 * Settings related to players as account owners.
 */
final class Config implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /**
     * @param list<string> $infoNames The names of the info objects to expose.
     */
    public function __construct(
        public array $infoNames,
    ) {}

    public static function fromSingletonArgs(Raw $raw, Schema\Config $schema) : self {
        return new self(
            infoNames: [Constants::CURRENCY_DEFAULT_INFO], // TODO refactor: merge with analytics cache
        );
    }
}
