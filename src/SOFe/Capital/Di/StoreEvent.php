<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use pocketmine\event\Event;

/**
 * Called when a new object (other than the AwaitStd instance) is stored into the TypeMap.
 */
final class StoreEvent extends Event {
    public function __construct(
        private Context $context,
        private Singleton $object,
    ) {
    }

    public function getContext() : Context {
        return $this->context;
    }

    public function getObject() : Singleton {
        return $this->object;
    }
}
