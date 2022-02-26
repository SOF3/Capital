<?php

declare(strict_types=1);

namespace SOFe\Capital;

use pocketmine\event\Event;
use pocketmine\player\Player;

final class PostTransactionEvent extends Event {
    /**
     * @param array<string, string> $labels
     * @param list<Player> $involvedPlayers
     */
    public function __construct(
        private TransactionRef $ref,
        private AccountRef $src,
        private AccountRef $dest,
        private int $amount,
        private array $labels,
        private array $involvedPlayers,
    ) {
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

    /**
     * @return array<string, string>
     */
    public function getLabels() : array {
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
}
