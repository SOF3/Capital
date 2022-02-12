<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SOFe\Capital\Database\Database;

/**
 * @implements CacheType<UuidInterface, array<string, string>>
 */
final class AccountLabelCacheType implements CacheType {
    public function keyToString($key) : string {
        return $key->getBytes();
    }

    public function fetchEntry(Database $db, $key) : Generator {
        $value = yield from $db->getAccountAllLabels($key);

        return $value;
    }

    public function fetchEntries(Database $db, array $keys) : Generator {
        $ids = [];
        foreach ($keys as $key) {
            $ids[$key] = Uuid::fromBytes($key);
        }
        return yield from $db->getAccountListAllLabels($ids);
    }

    /**
     * @return ?VoidPromise
     */
    public function onEntryRefresh(string $key, $old, $new) : ?Generator {
        return null;
    }

    /**
     * @return ?VoidPromise
     */
    public function onEntryFree(string $key, $value) : ?Generator {
        return null;
    }
}
