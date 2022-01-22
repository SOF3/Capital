<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use Generator;
use Logger;
use pocketmine\player\Player;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Cache\Cache;
use SOFe\Capital\Cache\CacheHandle;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;

use function assert;

final class Session {
    private bool $closed = false;
    /** @var ?CacheHandle */
    private ?CacheHandle $cacheHandle = null;

    public function __construct(
        private Cache $cache,
        private Database $db,
        private Logger $logger,
        private Player $player,
    ) {}

    /**
     * @return VoidPromise
     */
    public function initCache() : Generator {
        $this->cacheHandle = yield from $this->cache->query(new LabelSelector([
            AccountLabels::PLAYER_UUID => $this->player->getUniqueId()->toString(),
            AccountLabels::PLAYER_INFO_NAME => LabelSelector::ANY_VALUE,
        ]));

        if($this->closed) {
            $this->cacheHandle->release();
        }
    }

    public function close() : void {
        $this->closed = true;
        if($this->cacheHandle !== null) {
            $this->cacheHandle->release();
        }
    }

    public function getInfo(string $name) : ?int {
        if($this->cacheHandle === null) {
            return null;
        }

        foreach($this->cacheHandle->getAccounts() as $account) {
            $labels = $account->getLabels();
            assert(isset($labels[AccountLabels::PLAYER_INFO_NAME]));
            if($labels[AccountLabels::PLAYER_INFO_NAME] === $name) {
                return $account->getValue();
            }
        }

        return null;
    }
}
