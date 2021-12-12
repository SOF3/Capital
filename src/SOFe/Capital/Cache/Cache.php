<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\LabelSelector;

final class Cache {
	private static ?self $instance = null;

	public static function getInstance() : self {
        return self::$instance ?? self::$instance = new self;
    }

    /** @var CacheInstance<LabelSelector, list<UuidInterface>> */
    private CacheInstance $labelSelectorCache;
    /** @var CacheInstance<UuidInterface, int> */
    private CacheInstance $accountCache;
    /** @var CacheInstance<UuidInterface, array<string, string>> */
    private CacheInstance $accountLabelCache;

    public function __construct() {
        $accountCache = new CacheInstance(new AccountCacheType);
        $this->accountCache = $accountCache;

        $accountLabelCache = new CacheInstance(new AccountLabelCacheType);
        $this->accountLabelCache = $accountLabelCache;

        $this->labelSelectorCache = new CacheInstance(new LabelSelectorCacheType($accountCache, $accountLabelCache));

        Await::g2c($this->accountCache->refreshLoop(100));
        Await::g2c($this->accountLabelCache->refreshLoop(1200));
        Await::g2c($this->labelSelectorCache->refreshLoop(1200));
    }

    /**
     * @return Generator<mixed, mixed, mixed, CacheHandle>
     */
    public function query(LabelSelector $labelSelector) : Generator {
        yield from $this->labelSelectorCache->fetch($labelSelector);
        return new CacheHandle($labelSelector);
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
