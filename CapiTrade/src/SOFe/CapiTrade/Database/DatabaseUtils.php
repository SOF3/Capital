<?php

declare(strict_types=1);

namespace SOFe\CapiTrade\Database;

use Generator;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

final class DatabaseUtils implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public function __construct(private Database $db) {
        $this->db->getDataConnector()->executeGeneric()
    }

    public static function fromSingletonArgs(Database $db) : Generator{
        $self = new self($db);

        yield from $self->init();

        return new self;
    }
}
