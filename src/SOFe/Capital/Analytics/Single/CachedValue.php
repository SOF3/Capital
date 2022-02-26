<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics\Single;

use Closure;
use Generator;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use SOFe\InfoAPI\NumberInfo;

final class CachedValue {
    public function __construct(public ?float $value) {
    }

    public function asInfo() : ?NumberInfo {
        return $this->value !== null ? new NumberInfo($this->value) : null;
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

            yield from $std->sleep($cache->updateFrequencyTicks);
        }
    }
}
