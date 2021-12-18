<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use Logger;
use PrefixedLogger;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\MainClass;
use SOFe\Capital\Singleton;

final class Cache implements Singleton {
    private Logger $logger;

    /** @var CacheInstance<LabelSelector, list<UuidInterface>> */
    private CacheInstance $labelSelectorCache;
    /** @var CacheInstance<UuidInterface, int> */
    private CacheInstance $accountCache;
    /** @var CacheInstance<UuidInterface, array<string, string>> */
    private CacheInstance $accountLabelCache;

    public function __construct(MainClass $plugin, Database $db, AwaitStd $std) {
        $this->logger = new PrefixedLogger($plugin->getLogger(), "Cache");

        $accountCache = new CacheInstance($db, $std, new AccountCacheType);
        $this->accountCache = $accountCache;

        $accountLabelCache = new CacheInstance($db, $std, new AccountLabelCacheType);
        $this->accountLabelCache = $accountLabelCache;

        $this->labelSelectorCache = new CacheInstance($db, $std, new LabelSelectorCacheType($accountCache, $accountLabelCache));

        Await::g2c($this->accountCache->refreshLoop(100));
        Await::g2c($this->accountLabelCache->refreshLoop(1200));
        Await::g2c($this->labelSelectorCache->refreshLoop(1200));
    }

    public function getLogger() : Logger {
        return $this->logger;
    }

    /**
     * @return Generator<mixed, mixed, mixed, CacheHandle>
     */
    public function query(LabelSelector $labelSelector) : Generator {
        yield from $this->labelSelectorCache->fetch($labelSelector);
        return new CacheHandle($this, $labelSelector);
    }

    /**
     * @return CacheInstance<LabelSelector, list<UuidInterface>>
     */
    public function getLabelSelectorCache() : CacheInstance {
        return $this->labelSelectorCache;
    }

    /**
     * @return CacheInstance<UuidInterface, int>
     */
    public function getAccountCache() : CacheInstance {
        return $this->accountCache;
    }

    /**
     * @return CacheInstance<UuidInterface, array<string, string>>
     */
    public function getAccountLabelCache() : CacheInstance {
        return $this->accountLabelCache;
    }
}
