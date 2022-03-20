<?php

declare(strict_types=1);

namespace SOFe\CapiTrade;

use Generator;
use RuntimeException;

final class ExecuteHandle {
    private bool $used = false;

    private function __construct(private ShopExecuteEvent $event) {
    }

    /**
     * @internal
     */
    public static function internalInit(ShopExecuteEvent $event) : self {
        return new self($event);
    }

    /**
     * Returns whether the event is admitted or rejected.
     * @return Generator<mixed, mixed, mixed, bool>
     */
    public function admit() : Generator {
        if ($this->used) {
            throw new RuntimeException("Cannot admit/reject the same ExecuteHandle twice");
        }

        return yield from $this->event->internalAdmit();
    }

    public function reject(ShopRejection $rejection) : void {
        if ($this->used) {
            throw new RuntimeException("Cannot admit/reject the same ExecuteHandle twice");
        }

        $this->event->internalReject($rejection);
    }

    public function done() : void {
        $this->event->internalDone();
    }
}
