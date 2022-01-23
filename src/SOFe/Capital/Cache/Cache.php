<?php

declare(strict_types=1);

namespace SOFe\Capital\Cache;

use Generator;
use Logger;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\LabelSelector;

final class Cache implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /** @var Instance<LabelSelector, list<UuidInterface>> */
    private Instance $labelSelectorCache;
    /** @var Instance<UuidInterface, int> */
    private Instance $accountCache;
    /** @var Instance<UuidInterface, array<string, string>> */
    private Instance $accountLabelCache;

    public function __construct(
        private Logger $logger,
        Database $db,
        AwaitStd $std,
        Config $config,
    ) {
        $accountCache = new Instance($db, $std, new AccountCacheType);
        $this->accountCache = $accountCache;

        $accountLabelCache = new Instance($db, $std, new AccountLabelCacheType);
        $this->accountLabelCache = $accountLabelCache;

        $this->labelSelectorCache = new Instance($db, $std, new LabelSelectorCacheType($accountCache, $accountLabelCache));

        Await::g2c($this->accountCache->refreshLoop($config->accountBalanceRefreshInterval));
        Await::g2c($this->accountLabelCache->refreshLoop($config->labelSetRefreshInterval));
        Await::g2c($this->labelSelectorCache->refreshLoop($config->selectorMatchRefreshInterval));
    }

    public function getLogger() : Logger {
        return $this->logger;
    }

    /**
     * @return Generator<mixed, mixed, mixed, Handle>
     */
    public function query(LabelSelector $labelSelector) : Generator {
        yield from $this->labelSelectorCache->fetch($labelSelector);
        return new Handle($this, $labelSelector);
    }

    /**
     * @return Instance<LabelSelector, list<UuidInterface>>
     */
    public function getLabelSelectorCache() : Instance {
        return $this->labelSelectorCache;
    }

    /**
     * @return Instance<UuidInterface, int>
     */
    public function getAccountCache() : Instance {
        return $this->accountCache;
    }

    /**
     * @return Instance<UuidInterface, array<string, string>>
     */
    public function getAccountLabelCache() : Instance {
        return $this->accountLabelCache;
    }
}
