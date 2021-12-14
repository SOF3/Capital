<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use RuntimeException;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\MainClass;

final class CacheHandle {
    private bool $released = false;

    public function __construct(
        private LabelSelector $labelSelector,
    ) {}

    /**
     * @return list<CachedAccount>
     */
    public function getAccounts() : array {
        $cache = Cache::getInstance();
        $uuids = $cache->getLabelSelectorCache()->assertFetched($this->labelSelector);

        $accounts = [];
        foreach($uuids as $uuid) {
            $balance = $cache->getAccountCache()->assertFetched($uuid);
            $labels = $cache->getAccountLabelCache()->assertFetched($uuid);
            $accounts[] = new CachedAccount($uuid, $balance, $labels);
        }

        return $accounts;
    }

    public function release() : void {
        if($this->released) {
            throw new RuntimeException("Attempt to release the same CacheHandle twice");
        }

        Cache::getInstance()->getLabelSelectorCache()->free($this->labelSelector);
        $this->released = true;
    }

    public function __destruct() {
        if(!$this->released) {
            MainClass::getInstance()->getLogger()->warning("CacheHandle ({$this->labelSelector->debugDisplay()}) leak detected");
        }
    }
}
