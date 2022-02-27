<?php

declare(strict_types=1);

namespace SOFe\Capital;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\player\Player;

final class TransactionEvent extends Event implements Cancellable {
    use CancellableTrait;

    /**
     * @param list<Player> $involvedPlayers
     */
    public function __construct(
        private AccountRef $src,
        private AccountRef $dest,
        private int $amount,
        private LabelSet $labels,
        private array $involvedPlayers,
    ) {
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

    public function setAmount(int $amount) : void {
        $this->amount = $amount;
    }

    public function getLabels() : LabelSet {
        return $this->labels;
    }

    public function setLabels(LabelSet $labels) : void {
        $this->labels = $labels;
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
