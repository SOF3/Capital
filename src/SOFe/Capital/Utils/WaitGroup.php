<?php

declare(strict_types=1);

namespace SOFe\Capital\Utils;

use Closure;
use Generator;
use RuntimeException;
use SOFe\AwaitGenerator\Await;

final class WaitGroup {
    private int $count = 0;
    /** @var null|list<Closure(): void> */
    private ?array $onDone = [];

    public function add(int $count = 1) : void {
        if ($this->onDone === null) {
            throw new RuntimeException("WaitGroup is done");
        }

        $this->count += $count;
    }

    public function done() : void {
        $this->count -= 1;
        if ($this->count < 0) {
            throw new RuntimeException("WaitGroup::done called too many times");
        }

        $this->closeIfZero();
    }

    /**
     * @return VoidPromise
     */
    public function wait() : Generator {
        if ($this->onDone === null) {
            return;
        }

        $this->onDone[] = yield Await::RESOLVE;
        yield Await::ONCE;
    }

    public function closeIfZero() : void {
        if ($this->count === 0) {
            $onDone = $this->onDone;
            $this->onDone = null;

            if ($onDone !== null) {
                foreach ($onDone as $f) {
                    $f();
                }
            }
        }
    }
}
