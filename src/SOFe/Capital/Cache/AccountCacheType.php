<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SOFe\Capital\Database\Database;

/**
 * @implements CacheType<UuidInterface, int>
 */
final class AccountCacheType implements CacheType {
    public function keyToString($key): string {
        return $key->getBytes();
    }

    public function fetchEntry($key): Generator {
        $value = yield from Database::getInstance()->getAccountValue($key);

        return $value;
    }

    public function fetchEntries(array $keys): Generator {
        $ids = [];
        foreach($keys as $key) {
            $ids[$key] = Uuid::fromBytes($key);
        }
        return yield from Database::getInstance()->getAccountListValues($ids);
    }

    /**
     * @return ?VoidPromise
     */
    public function onEntryRefresh(string $key, $old, $new): ?Generator {
        return null;
    }

    /**
     * @return ?Generator<mixed, mixed, mixed, void>
     */
    public function onEntryFree(string $key, $value): ?Generator {
        return null;
    }
}
