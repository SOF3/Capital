<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Single;

use Closure;
use Generator;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use SOFe\InfoAPI\NumberInfo;

final class CachedValue {
    /** @var list<Closure(): void> Called and cleared when value is refreshed next time */
    private array $waits = [];

    /** @var Closure(): void Called when refresh is requested. */
    private ?Closure $refreshNow;

    public function __construct(public ?float $value) {
    }

    public function asInfo() : ?NumberInfo {
        return $this->value !== null ? new NumberInfo($this->value) : null;
    }

    /**
     * @return VoidPromise
     */
    public function waitForRefresh() : Generator {
        $this->waits[] = yield Await::RESOLVE;
        yield Await::ONCE;
    }

    /**
     * @template P
     * @param Closure(): bool $continue Whether to continue the loop.
     * @param Cached<P> $cache The cached query.
     * @param P $p
     * @return VoidPromise
     */
    public function loop(Closure $continue, AwaitStd $std, Cached $cache, $p, Database $db) : Generator {
        while ($continue()) {
            $this->value = yield from $cache->query->fetch($p, $db);

            $waits = $this->waits;
            $this->waits = [];
            foreach ($waits as $wait) {
                $wait();
            }

            [$which, ] = yield from Await::race([$this->waitWakeup(), $std->sleep($cache->updateFrequencyTicks)]);
        }
    }

    /**
     * Request a refresh to take place immediately.
     * Does nothing if the loop is refreshing.
     */
    public function refreshNow() : void {
        $closure = $this->refreshNow;
        if ($closure !== null) {
            $closure();
        }
    }

    /**
     * Returns a promise that resolves when a wakeup request is received.
     * @return VoidPromise
     */
    private function waitWakeup() : Generator {
        $this->refreshNow = yield Await::RESOLVE;
        yield Await::ONCE;
        $this->refreshNow = null; // This line is never run if refreshNow is not called, but it is fine.
    }
}
