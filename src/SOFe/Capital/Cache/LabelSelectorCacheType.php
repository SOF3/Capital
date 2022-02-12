<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;
use function array_diff;
use function count;

/**
 * @implements CacheType<LabelSelector, list<UuidInterface>>
 */
final class LabelSelectorCacheType implements CacheType {
    /**
     * @param Instance<UuidInterface, int> $accountCache
     * @param Instance<UuidInterface, array<string, string>> $accountLabelCache
     */
    public function __construct(
        private Instance $accountCache,
        private Instance $accountLabelCache,
    ) {
    }

    public function keyToString($key) : string {
        return $key->toBytes();
    }

    public function fetchEntry(Database $db, $selector) : Generator {
        $accounts = yield from $db->findAccounts($selector);

        $promises = [];
        foreach ($accounts as $account) {
            $promises[] = $this->accountCache->fetch($account);
            $promises[] = $this->accountLabelCache->fetch($account);
        }
        if (count($promises) > 0) {
            yield from Await::all($promises);
        }

        return $accounts;
    }

    public function fetchEntries(Database $db, array $keys) : Generator {
        $promises = [];
        $output = [];

        foreach ($keys as $key) {
            $promises[] = (function() use (&$output, $key, $db) {
                $selector = LabelSelector::parseEntries($key);
                $accounts = yield from $db->findAccounts($selector);
                $output[$key] = $accounts;
            })();
        }

        if (count($promises) > 0) {
            yield from Await::all($promises);
        }
        return $output;
    }

    public function onEntryRefresh(string $key, $old, $new) : ?Generator {
        $removed = array_diff($old, $new);
        $added = array_diff($new, $old);

        $promises = [];

        foreach ($removed as $account) {
            $this->accountCache->free($account);
            $this->accountLabelCache->free($account);
        }
        foreach ($added as $account) {
            $promises[] = $this->accountCache->fetch($account);
            $promises[] = $this->accountLabelCache->fetch($account);
        }

        if (count($promises) > 0) {
            yield from Await::all($promises);
        }
    }

    public function onEntryFree(string $key, $accounts) : ?Generator {
        foreach ($accounts as $account) {
            $this->accountCache->free($account);
            $this->accountLabelCache->free($account);
        }
        return null;
    }
}
