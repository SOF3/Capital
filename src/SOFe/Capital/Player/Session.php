<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Cache\Cache;
use SOFe\Capital\Cache\CacheHandle;
use SOFe\Capital\LabelSelector;

final class Session {
    private bool $closed = false;
    /** @var ?CacheHandle */
    private ?CacheHandle $cacheHandle = null;

    public function __construct(
        private Player $player,
    ) {
        Await::f2c(function() {
            $this->cacheHandle = yield from Cache::getInstance()->query(new LabelSelector([
                AccountLabels::PLAYER_UUID => $this->player->getUniqueId()->toString(),
                AccountLabels::PLAYER_INFO_NAME => LabelSelector::ANY_VALUE,
            ]));
            if($this->closed) {
                $this->cacheHandle->release();
            }
        });
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
            if(isset($labels[AccountLabels::PLAYER_INFO_NAME]) && $labels[AccountLabels::PLAYER_INFO_NAME] === $name) {
                return $account->getValue();
            }
        }

        return null;
    }
}
