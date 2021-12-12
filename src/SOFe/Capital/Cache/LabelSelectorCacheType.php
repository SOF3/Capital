<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use function array_diff;
use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;

/**
 * @implements CacheType<LabelSelector, list<UuidInterface>>
 */
final class LabelSelectorCacheType implements CacheType {
    /**
     * @param CacheInstance<UuidInterface, int> $accountCache
     * @param CacheInstance<UuidInterface, array<string, string>> $accountLabelCache
     */
    public function __construct(
        private CacheInstance $accountCache,
        private CacheInstance $accountLabelCache,
    ) {}

    public function keyToString($key): string {
        return $key->toBytes();
    }

    public function fetchEntry($selector): Generator {
        $accounts = yield from Database::getInstance()->findAccountN($selector);

        $promises = [];
        foreach($accounts as $account) {
            $promises[] = $this->accountCache->fetch($account);
            $promises[] = $this->accountLabelCache->fetch($account);
        }
        yield from Await::all($promises);

        return $accounts;
    }

    public function fetchEntries(array $keys): Generator {
        $promises = [];
        $output = [];

        foreach($keys as $key) {
            $promises[] = (function() use(&$output, $key) {
                $selector = LabelSelector::parseEntries($key);
                $accounts = yield from Database::getInstance()->findAccountN($selector);
                $output[$key] = $accounts;
            })();
        }

        yield from Await::all($promises);
        return $output;
    }

    public function onEntryRefresh(string $key, $old, $new): ?Generator {
        $removed = array_diff($old, $new);
        $added = array_diff($new, $old);

        $promises = [];

        foreach($removed as $account) {
            $this->accountCache->free($account);
            $this->accountLabelCache->free($account);
        }
        foreach($added as $account) {
            $promises[] = $this->accountCache->fetch($account);
            $promises[] = $this->accountLabelCache->fetch($account);
        }

        yield from Await::all($promises);
    }

    public function onEntryFree(string $key, $accounts): ?Generator {
        foreach($accounts as $account) {
            $this->accountCache->free($account);
            $this->accountLabelCache->free($account);
        }
        return null;
    }
}
