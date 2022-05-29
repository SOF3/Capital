<?php

declare(strict_types=1);

namespace SOFe\CapiTrade;

use Closure;
use Generator;
use pocketmine\event\Cancellable;
use pocketmine\event\Event;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Loading;
use SOFe\Capital\Utils\WaitGroup;

final class ShopExecuteEvent extends Event implements Cancellable {
    /** This WaitGroup completes when all executors have admitted. */
    private WaitGroup $onAdmit;
    /** @var Loading<ShopRejection> Completes when any executor has rejected. */
    private Loading $onReject;
    /** @var Closure(ShopRejection): void */
    private Closure $rejectFunc;

    /** @var Loading<bool> */
    private Loading $onTransactionComplete;

    /** This WaitGroup completes when all executors have completed after admission confirmation. */
    private WaitGroup $onDone;

    /**
     * @param ShopRef $shopId The shop ID
     * @param array<string, string> $labels The shop labels
     * @param Player $user The user who executed the shop
     * @param Closure(): Generator<mixed, mixed, mixed, bool> $execute Execute the shop transaction
     */
    public function __construct(
        private ShopRef $shopId,
        private array $labels,
        private Player $user,
        Closure $execute,
    ) {
        $this->onAdmit = new WaitGroup;
        $this->onReject = new Loading(function() : Generator {
            return yield from Await::promise(fn($resolve) => $this->rejectFunc = $resolve);
        });
        $this->onTransactionComplete = new Loading(function() use ($execute) : Generator {
            [$rejected,] = yield from Await::race([$this->onAdmit->wait(), $this->onReject->get()]);
            if ($rejected === 1) {
                return false;
            }

            $transactionOk = yield from $execute();
            return $transactionOk;
        });
        $this->onDone = new WaitGroup;
    }

    public function getShopId() : ShopRef {
        return $this->shopId;
    }

    /**
     * @return array<string, string>
     */
    public function getLabels() : array {
        return $this->labels;
    }

    public function getUser() : Player {
        return $this->user;
    }

    /**
     * @param Closure(): Generator<mixed, mixed, mixed, ShopRejection|null> $hold
     * @param Closure(): Generator<mixed, mixed, mixed, void> $commit
     * @param Closure(): Generator<mixed, mixed, mixed, void> $rollback
     */
    public function add(Closure $hold, Closure $commit, Closure $rollback) : void {
        Await::f2c(function() use ($hold, $commit, $rollback) : Generator {
            $handle = $this->addExecutor();

            $rejection = yield from $hold();
            if ($rejection === null) {
                $ok = yield from $handle->admit();
                if ($ok) {
                    yield from $commit();
                } else {
                    yield from $rollback();
                }
                $handle->done();
            } else {
                $handle->reject($rejection);
            }
        });
    }

    /**
     * Adds an executor to this event.
     *
     * This function returns a new `ExecuteHandle`,
     * and adds the executor to the admission wait group.
     */
    public function addExecutor() : ExecuteHandle {
        $this->onAdmit->add();
        return ExecuteHandle::internalInit($this);
    }

    public function isCancelled() : bool {
        return $this->onReject->getSync(null) !== null;
    }

    public function getRejection() : ?ShopRejection {
        return $this->onReject->getSync(null);
    }

    /**
     * @internal
     * @return Generator<mixed, mixed, mixed, bool>
     */
    public function internalAdmit() : Generator {
        $this->onAdmit->done();
        [$rejected,] = yield from Await::race([$this->onAdmit->wait(), $this->onReject->get()]);
        if ($rejected === 1) {
            return false;
        }

        return yield from $this->onTransactionComplete->get();
    }

    /**
     * @internal
     */
    public function internalReject(ShopRejection $rejection) : void {
        if ($this->isCancelled()) {
            return;
        }
        ($this->rejectFunc)($rejection);
    }

    /**
     * @internal
     */
    public function internalDone() : void {
        $this->onDone->done();
    }

    /**
     * Waits for the event to finish.
     * Returns null if the shop was executed successfully,
     * the ShopRejection if the execution failed.
     *
     * @return Generator<mixed, mixed, mixed, ShopRejection|null>
     */
    public function waitDone() : Generator {
        [$done, $rejection] = yield from Await::race([
            $this->onReject->get(),
            $this->onDone->wait(),
        ]);

        if ($done === 0) {
            /** @var ShopRejection $rejection */
            return $rejection;
        }

        return null;
    }
}
