<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use SOFe\Capital\Utils\WaitGroup;

final class CompleteEvent extends Event {
    use CancellableTrait;

    private WaitGroup $refreshWg;

    public function __construct(private int $updateCount) {
        $this->refreshWg = new WaitGroup;
    }

    /**
     * Number of accounts migrated.
     */
    public function getUpdateCount() : int {
        return $this->updateCount;
    }


    /**
     * Add to this wait group if you want to refresh the accounts involved in this migration.
     * Wait on this wait group to ensure all accounts involved in this migration are refreshed.
     */
    public function getRefreshWaitGroup() : WaitGroup {
        return $this->refreshWg;
    }
}
