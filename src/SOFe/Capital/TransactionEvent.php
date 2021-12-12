<?php

declare(strict_types=1);

namespace SOFe\Capital;

use pocketmine\event\Event;

final class TransactionEvent extends Event {
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        private AccountRef $src,
        private AccountRef $dest,
        private int $amount,
        private array $labels,
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

    /**
     * @return array<string, string>
     */
    public function getLabels() : array {
        return $this->labels;
    }

    /**
     * @param array<string, string> $labels
     */
    public function setLabels(array $labels) : void {
        $this->labels = $labels;
    }
}
