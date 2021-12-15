<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use Generator;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Cache\Cache;
use SOFe\Capital\Cache\CacheHandle;
use SOFe\Capital\Config;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\PlayerInfo;

final class Session {
    private bool $closed = false;
    /** @var ?CacheHandle */
    private ?CacheHandle $cacheHandle = null;

    public function __construct(
        private Player $player,
    ) {
        Await::f2c(function() {
            yield from $this->initAccounts();

            $this->cacheHandle = yield from Cache::getInstance()->query(new LabelSelector([
                AccountLabels::PLAYER_UUID => $this->player->getUniqueId()->toString(),
                AccountLabels::PLAYER_INFO_NAME => LabelSelector::ANY_VALUE,
            ]));

            if($this->closed) {
                $this->cacheHandle->release();
            }
        });
    }

    /**
     * @return VoidPromise
     */
    private function initAccounts() : Generator {
        $config = Config::getInstance()->player->initialAccounts;
        $db = Database::getInstance();

        $player = new PlayerInfo($this->player);
        $context = new InitialAccountLabelContextInfo("capital", [
            "player" => $player,
        ]);

        $promises = [];

        foreach($config as $spec) {
            $promises[] = (static function() use($spec, $db, $context) {
                $selectorLabels = $spec->selectorLabels->transform($context);
                $migrationLabels = $spec->migrationLabels->transform($context);
                $initialLabels = $spec->initialLabels->transform($context);
                $overwriteLabels = $spec->overwriteLabels->transform($context);

                $accounts = yield from $db->findAccountN($selectorLabels);

                $promises = [];
                if(count($accounts) > 0) {
                    foreach($accounts as $id) {
                        foreach($overwriteLabels->getEntries() as $k => $v) {
                            $promises[] = $db->setAccountLabel($id, $k, $v);
                        }
                    }
                } else {
                    // check for fallback migration account
                    $accounts = yield from $db->findAccountN($migrationLabels);
                    if(count($accounts) > 0) {
                        foreach($accounts as $id) {
                            foreach($selectorLabels->getEntries() as $k => $v) {
                                $promises[] = $db->setAccountLabel($id, $k, $v);
                            }
                            foreach($overwriteLabels->getEntries() as $k => $v) {
                                $promises[] = $db->setAccountLabel($id, $k, $v);
                            }
                        }
                    } else {
                        yield from $db->createAccount($spec->initialValue, $selectorLabels->getEntries() + $initialLabels->getEntries() + $overwriteLabels->getEntries());
                    }
                }

                yield from Await::all($promises);
            })();
        }

        yield from Await::all($promises);
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
