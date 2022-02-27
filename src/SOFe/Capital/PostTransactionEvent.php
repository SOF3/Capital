<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

final class PostTransactionEvent extends Event {
    private int $refreshCount = 0;
    /** @var Closure(): void */
    private ?Closure $onRefreshZero = null;

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
     * Notifies the event caller that a refresh operation is scheduled.
     * Call `doneRefresh` when the refresh is complete.
     * Must be called during event dispatch synchronously.
     */
    public function addRefresh() : void {
        $this->refreshCount += 1;
    }

    /**
     * Notifies the event caller that a refresh operation is complete.
     * This method should be called exactly once after `addRefresh`.
     * May be called synchronously during event dispatch, or asynchronously.
     */
    public function doneRefresh() : void {
        $this->refreshCount -= 1;
        if ($this->refreshCount === 0 && $this->onRefreshZero !== null) {
            ($this->onRefreshZero)();
            $this->onRefreshZero = null;
        }
    }

    /**
     * Called by the event caller after event dispatch.
     * Resolves when all registered refresh operations are done.
     * @return VoidPromise
     */
    public function waitRefreshComplete() : Generator {
        if ($this->refreshCount > 0) {
            $this->onRefreshZero = yield Await::RESOLVE;
            yield Await::ONCE;
            $this->onRefreshZero = null;
        }
    }
}
