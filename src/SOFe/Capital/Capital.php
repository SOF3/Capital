<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Generator;
use InvalidArgumentException;
use Logger;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

use function array_map;
use function count;

final class Capital implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const VERSION = "0.1.0";

    public function __construct(
        private Logger $logger,
        private Database $database,
    ) {
    }

    /**
     * @param array<string, string> $labels
     * @return Generator<mixed, mixed, mixed, TransactionRef> the transaction ID
     */
    public function transact(AccountRef $src, AccountRef $dest, int $amount, array $labels) : Generator {
        $event = new TransactionEvent($src, $dest, $amount, $labels);
        $event->call();
        $amount = $event->getAmount();
        $labels = $event->getLabels();

        $id = yield from $this->database->doTransaction($src->getId(), $dest->getId(), $amount);

        $promises = [];
        foreach ($labels as $labelName => $labelValue) {
            $promises[] = $this->database->setTransactionLabel($id, $labelName, $labelValue);
        }
        yield from Await::all($promises);

        return new TransactionRef($id);
    }

    /**
     * @param array<string, string> $labels1
     * @param array<string, string> $labels2
     * @return Generator<mixed, mixed, mixed, array{TransactionRef, TransactionRef}> the transaction IDs
     */
    public function transact2(
        AccountRef $src1, AccountRef $dest1, int $amount1, array $labels1,
        AccountRef $src2, AccountRef $dest2, int $amount2, array $labels2,
        ?UuidInterface $uuid1 = null, ?UuidInterface $uuid2 = null,
    ) : Generator {
        $event = new TransactionEvent($src1, $dest1, $amount1, $labels1);
        $event->call();
        $amount1 = $event->getAmount();
        $labels1 = $event->getLabels();

        $event = new TransactionEvent($src2, $dest2, $amount2, $labels2);
        $event->call();
        $amount2 = $event->getAmount();
        $labels2 = $event->getLabels();

        $ids = yield from $this->database->doTransaction2(
            $src1->getId(), $dest1->getId(), $amount1,
            $src2->getId(), $dest2->getId(), $amount2,
            AccountLabels::VALUE_MIN, AccountLabels::VALUE_MAX,
            $uuid1, $uuid2,
        );

        $promises = [];
        foreach ($labels1 as $labelName => $labelValue) {
            $promises[] = $this->database->setTransactionLabel($ids[0], $labelName, $labelValue);
        }
        foreach ($labels2 as $labelName => $labelValue) {
            $promises[] = $this->database->setTransactionLabel($ids[1], $labelName, $labelValue);
        }
        yield from Await::all($promises);

        return [new TransactionRef($ids[0]), new TransactionRef($ids[1])];
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<AccountRef>>
     */
    public function findAccounts(LabelSelector $selector) : Generator {
        $accounts = yield from $this->database->findAccounts($selector);

        return array_map(fn($account) => new AccountRef($account), $accounts);
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     */
    public function getBalance(AccountRef $account) : Generator {
        return yield from $this->database->getAccountValue($account->getId());
    }

    /**
     * @param array<AccountRef> $accounts
     * @return Generator<mixed, mixed, mixed, array<int>>
     */
    public function getBalances(array $accounts) : Generator {
        $ids = [];
        foreach ($accounts as $key => $account) {
            $ids[$key] = $account->getId();
        }

        return yield from $this->database->getAccountListValues($ids);
    }

    /**
     * @return Generator<mixed, mixed, mixed, AccountRef>
     */
    public function getOracle(string $name) : Generator {
        $labels = [
            AccountLabels::ORACLE => $name,
        ];

        $accounts = yield from self::findAccounts(new LabelSelector($labels));
        if (count($accounts) > 0) {
            return $accounts[0];
        }

        $this->logger->debug("Initialized oracle $name");

        // Do not apply valueMin and valueMax on this account,
        // otherwise we will get failing transactions and it's no longer an oracle.
        $account = yield from $this->database->createAccount(0, $labels);
        return new AccountRef($account);
    }

    /**
     * @param array<AccountQueryMetric> $metrics
     * @return Generator<mixed, mixed, mixed, array<int|float>>
     */
    public function getAccountMetrics(LabelSelector $labelSelector, array $metrics) : Generator {
        return yield from $this->database->aggregateAccounts($labelSelector, $metrics);
    }

    /**
     * @param list<TransactionQueryMetric> $metrics
     * @return Generator<mixed, mixed, mixed, array<int|float>>
     */
    public function getTransactionMetrics(LabelSelector $labelSelector, array $metrics) : Generator {
        return yield from $this->database->aggregateTransactions($labelSelector, $metrics);
    }

    /**
     * Finds the accounts for an incomplete schema, querying the command sender for information if necessary.
     * Creates or migrates an account if the player does not have the required account.
     *
     * @param Player $player The player that owns the returned account.
     * @param Schema\Schema $schema The incomplete schema.
     * @param list<string> $args The command arguments for account selection.
     * @param CommandSender $sender The command sender to ask for more information if necessary.
     * @return Generator<mixed, mixed, mixed, array<AccountRef>>
     * @throws InvalidArgumentException if the arguments cannot be inferred.
     */
    public function findAccountsIncomplete(Player $player, Schema\Schema $schema, array &$args, CommandSender $sender) : Generator {
        $complete = yield from Schema\Utils::fromCommand($schema, $args, $sender);
        $accounts = yield from Schema\Utils::lazyCreate($complete, $this->database, $player);
        return $accounts;
    }

    /**
     * Finds the accounts for a complete schema.
     * Creates or migrates an account if the player does not have the required account.
     *
     * @param Player $player The player that owns the returned account.
     * @param Schema\Complete $complete The complete schema.
     * @return Generator<mixed, mixed, mixed, array<AccountRef>>
     */
    public function findAccountsComplete(Player $player, Schema\Complete $complete) : Generator {
        $accounts = yield from Schema\Utils::lazyCreate($complete, $this->database, $player);
        return $accounts;
    }
}
