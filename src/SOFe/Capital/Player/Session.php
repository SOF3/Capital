<?php

declare(strict_types=1);

namespace SOFe\Capital\Player;

use Generator;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Cache\Cache;
use SOFe\Capital\Cache\CacheHandle;
use SOFe\Capital\Database\Database;
use SOFe\Capital\LabelSelector;
use SOFe\InfoAPI\PlayerInfo;
use function count;

final class Session {
    private bool $closed = false;
    /** @var ?CacheHandle */
    private ?CacheHandle $cacheHandle = null;

    public function __construct(
        private Cache $cache,
        private Config $config,
        private Database $db,
        private Player $player,
    ) {
        Await::f2c(function() {
            yield from $this->initAccounts();

            $this->cacheHandle = yield from $this->cache->query(new LabelSelector([
                AccountLabels::PLAYER_UUID => $this->player->getUniqueId()->toString(),
                AccountLabels::PLAYER_INFO_NAME => LabelSelector::ANY_VALUE,
            ]));

            $event = new CacheReadyEvent($this->player);
            $event->call();

            if($this->closed) {
                $this->cacheHandle->release();
            }
        });
    }

    /**
     * @return VoidPromise
     */
    private function initAccounts() : Generator {
        $config = $this->config->initialAccounts;

        $player = new PlayerInfo($this->player);
        $context = new InitialAccountLabelContextInfo("capital", [
            "player" => $player,
        ]);

        $promises = [];

        $createdCount = 0;
        $migratedCount = 0;
        $matchingCount = 0;

        foreach($config as $spec) {
            $promises[] = (function() use($spec, $context, &$createdCount, &$migratedCount, &$matchingCount) {
                $selectorLabels = $spec->selectorLabels->transform($context);
                $migrationLabels = $spec->migrationLabels->transform($context);
                $initialLabels = $spec->initialLabels->transform($context);
                $overwriteLabels = $spec->overwriteLabels->transform($context);

                $accounts = yield from $this->db->findAccounts($selectorLabels);

                $promises = [];
                if(count($accounts) > 0) {
                    // overwrite account labels with $overwriteLabels
                    foreach($accounts as $id) {
                        foreach($overwriteLabels->getEntries() as $k => $v) {
                            $promises[] = $this->db->setAccountLabel($id, $k, $v);
                        }
                    }

                    $matchingCount++;
                } else {
                    // check for fallback migration account
                    $accounts = yield from $this->db->findAccounts($migrationLabels);

                    if(count($accounts) > 0) {
                        // perform migration
                        foreach($accounts as $id) {
                            foreach($selectorLabels->getEntries() as $k => $v) {
                                $promises[] = $this->db->setAccountLabel($id, $k, $v);
                            }
                            foreach($overwriteLabels->getEntries() as $k => $v) {
                                $promises[] = $this->db->setAccountLabel($id, $k, $v);
                            }
                        }

                        $migratedCount++;
                    } else {
                        // create new account
                        yield from $this->db->createAccount($spec->initialValue, $selectorLabels->getEntries() + $initialLabels->getEntries() + $overwriteLabels->getEntries());

                        $createdCount++;
                    }
                }

                yield from Await::all($promises);
            })();
        }

        yield from Await::all($promises);

        $event = new AccountsInitedEvent($this->player, $createdCount, $migratedCount, $matchingCount);
        $event->call();
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
