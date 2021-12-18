<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use RuntimeException;
use SOFe\Capital\LabelSelector;

final class CacheHandle {
    private bool $released = false;

    public function __construct(
        private Cache $cache,
        private LabelSelector $labelSelector,
    ) {}

    /**
     * @return list<CachedAccount>
     */
    public function getAccounts() : array {
        $uuids = $this->cache->getLabelSelectorCache()->assertFetched($this->labelSelector);

        $accounts = [];
        foreach($uuids as $uuid) {
            $balance = $this->cache->getAccountCache()->assertFetched($uuid);
            $labels = $this->cache->getAccountLabelCache()->assertFetched($uuid);
            $accounts[] = new CachedAccount($uuid, $balance, $labels);
        }

        return $accounts;
    }

    public function release() : void {
        if($this->released) {
            throw new RuntimeException("Attempt to release the same CacheHandle twice");
        }

        $this->cache->getLabelSelectorCache()->free($this->labelSelector);
        $this->released = true;
    }

    public function __destruct() {
        if(!$this->released) {
            $this->cache->getLogger()->warning("CacheHandle ({$this->labelSelector->debugDisplay()}) leak detected");
        }
    }
}
