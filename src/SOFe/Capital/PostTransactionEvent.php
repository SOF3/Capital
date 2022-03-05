<?php

declare(strict_types=1);

namespace SOFe\Capital;

use pocketmine\event\Event;
use pocketmine\player\Player;
use SOFe\Capital\Utils\WaitGroup;

final class PostTransactionEvent extends Event {
    private WaitGroup $refreshWg;

    /**
     * @param list<Player> $involvedPlayers
     */
    public function __construct(
        private TransactionRef $ref,
        private AccountRef $src,
        private AccountRef $dest,
        private int $amount,
        private LabelSet $labels,
        private array $involvedPlayers,
    ) {
        $this->refreshWg = new WaitGroup;
    }

    public function getRef() : TransactionRef {
        return $this->ref;
    }

    public function getSrc() : AccountRef {
        return $this->src;
    }

    public function getDest() : AccountRef {
        return $this->dest;
    }

    public function getAmount() : int {
        return $this->amount;
    }

    public function getLabels() : LabelSet {
        return $this->labels;
    }

    /**
     * Returns the non-exhaustive list of players probably related to this transaction.
     * Used for hinting early account refresh.
     *
     * @return list<Player>
     */
    public function getInvolvedPlayers() : array {
        return $this->involvedPlayers;
    }

    /**
     * Add to this wait group if you want to refresh the accounts involved in this transaction.
     * Wait on this wait group to ensure all accounts involved in this transaction are refreshed.
     */
    public function getRefreshWaitGroup() : WaitGroup {
        return $this->refreshWg;
    }
}
