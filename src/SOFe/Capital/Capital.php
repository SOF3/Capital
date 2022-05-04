<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Closure;
use Generator;
use InvalidArgumentException;
use Logger;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;
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

    public const VERSION = Mod::API_VERSION;

    private Schema\Schema $globalSchema;

    public function __construct(
        private Logger $logger,
        private Database $database,
        Schema\Config $schemaConfig,
    ) {
        $this->globalSchema = $schemaConfig->schema;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function incompleteConfig(?array $config) : Schema\Schema {
        $arrayRef = new Config\ArrayRef($config ?? []);
        $parser = new Config\Parser($arrayRef, [], false);
        return $this->globalSchema->cloneWithConfig($parser);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function completeConfig(?array $config) : Schema\Complete {
        $arrayRef = new Config\ArrayRef($config ?? []);
        $parser = new Config\Parser($arrayRef, [], false);
        return $this->globalSchema->cloneWithCompleteConfig($parser);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function invariantConfig(?array $config) : Schema\Invariant {
        $arrayRef = new Config\ArrayRef($config ?? []);
        $parser = new Config\Parser($arrayRef, [], false);
        return $this->globalSchema->cloneWithInvariantConfig($parser);
    }

    /**
     * @param list<Player> $involvedPlayers
     * @return Generator<mixed, mixed, mixed, TransactionRef> the transaction ID
     */
    public function transact(AccountRef $src, AccountRef $dest, int $amount, LabelSet $labels, array $involvedPlayers, bool $awaitRefresh) : Generator {
        $event = new TransactionEvent($src, $dest, $amount, $labels, $involvedPlayers);
        $event->call();
        if ($event->isCancelled()) {
            throw new CapitalException(CapitalException::EVENT_CANCELLED);
        }

        $amount = $event->getAmount();
        $labels = $event->getLabels();

        $id = yield from $this->database->doTransaction($src->getId(), $dest->getId(), $amount);

        $promises = [];
        foreach ($labels->getEntries() as $labelName => $labelValue) {
            $promises[] = $this->database->transactionLabels()->set($id, $labelName, $labelValue);
        }
        yield from Await::all($promises);

        $ref = new TransactionRef($id);
        $event = new PostTransactionEvent($ref, $src, $dest, $amount, $labels, $involvedPlayers);
        $event->call();

        if ($awaitRefresh) {
            $wg = $event->getRefreshWaitGroup();
            $wg->closeIfZero();
            yield from $wg->wait();
        }

        return $ref;
    }

    /**
     * @param list<Player> $involvedPlayers
     * @return Generator<mixed, mixed, mixed, array{TransactionRef, TransactionRef}> the transaction IDs
     */
    public function transact2(
        AccountRef $src1, AccountRef $dest1, int $amount1, LabelSet $labels1,
        AccountRef $src2, AccountRef $dest2, int $amount2, LabelSet $labels2,
        array $involvedPlayers, bool $awaitRefresh,
        ?UuidInterface $uuid1 = null, ?UuidInterface $uuid2 = null,
    ) : Generator {
        $event = new TransactionEvent($src1, $dest1, $amount1, $labels1, $involvedPlayers);
        $event->call();
        if ($event->isCancelled()) {
            throw new CapitalException(CapitalException::EVENT_CANCELLED);
        }

        $amount1 = $event->getAmount();
        $labels1 = $event->getLabels();

        $event = new TransactionEvent($src2, $dest2, $amount2, $labels2, $involvedPlayers);
        $event->call();
        if ($event->isCancelled()) {
            throw new CapitalException(CapitalException::EVENT_CANCELLED);
        }

        $amount2 = $event->getAmount();
        $labels2 = $event->getLabels();

        $ids = yield from $this->database->doTransaction2(
            $src1->getId(), $dest1->getId(), $amount1,
            $src2->getId(), $dest2->getId(), $amount2,
            AccountLabels::VALUE_MIN, AccountLabels::VALUE_MAX,
            $uuid1, $uuid2,
        );

        $promises = [];
        foreach ($labels1->getEntries() as $labelName => $labelValue) {
            $promises[] = $this->database->transactionLabels()->set($ids[0], $labelName, $labelValue);
        }
        foreach ($labels2->getEntries() as $labelName => $labelValue) {
            $promises[] = $this->database->transactionLabels()->set($ids[1], $labelName, $labelValue);
        }
        yield from Await::all($promises);

        $ref1 = new TransactionRef($ids[1]);
        $event1 = new PostTransactionEvent($ref1, $src1, $dest1, $amount1, $labels1, $involvedPlayers);
        $event1->call();

        $ref2 = new TransactionRef($ids[1]);
        $event2 = new PostTransactionEvent($ref2, $src2, $dest2, $amount2, $labels2, $involvedPlayers);
        $event2->call();

        if ($awaitRefresh) {
            $event1->getRefreshWaitGroup()->closeIfZero();
            $event2->getRefreshWaitGroup()->closeIfZero();
            yield from Await::all([
                $event1->getRefreshWaitGroup()->wait(),
                $event2->getRefreshWaitGroup()->wait(),
            ]);
        }

        return [$ref1, $ref2];
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

    /**
     * @return VoidPromise
     */
    public function pay(
        Player $src,
        Player $dest,
        Schema\Complete $schema,
        int $amount,
        LabelSet $transactionLabels,
        bool $awaitRefresh = false,
    ) : Generator {
        $srcAccounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $src);
        $destAccounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $dest);

        $srcAccount = $srcAccounts[0]; // must have at least one because it was lazily created
        $destAccount = $destAccounts[0]; // must have at least one because it was lazily created

        yield from $this->transact($srcAccount, $destAccount, $amount, $transactionLabels, [$src, $dest], $awaitRefresh);
    }

    /**
     * Allows plugin developers to pay the destination player an amount depending on the source player balance
     * 
     * @param Closure (int) : int $convert
     * @return VoidPromise
     */
    public function payWithBalance(
        Player $src,
        Player $dest,
        Schema\Complete $schema,
        Closure $convert,
        LabelSet $transactionLabels,
        bool $awaitRefresh = false,
    ) : Generator {
        $srcAccounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $src);
        $destAccounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $dest);

        $srcAccount = $srcAccounts[0]; // must have at least one because it was lazily created
        $destAccount = $destAccounts[0]; // must have at least one because it was lazily created
        do{
        	try{
                $balance = yield from $this->getBalance($srcAccount);
                $amount = $convert($balance);
                yield from $this->transact($srcAccount, $destAccount, $amount, $transactionLabels, [$src, $dest], $awaitRefresh);
                $retry = false;
            } catch(CapitalException $e) {
                if ($e->getCode() === CapitalException::SOURCE_UNDERFLOW) {
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while($retry);
        
    }

    /**
     * Automatically appends `TransactionLabels::UNEQUAL_AUX` to $oracleTransactionLabels.
     *
     * @return VoidPromise
     */
    public function payUnequal(
        string $oracleName,
        Player $src,
        Player $dest,
        Schema\Complete $schema,
        int $srcDeduction,
        int $destAddition,
        LabelSet $directTransactionLabels,
        LabelSet $oracleTransactionLabels,
        bool $awaitRefresh = false,
    ) : Generator {
        if ($srcDeduction === $destAddition) {
            yield from $this->pay($src, $dest, $schema, $srcDeduction, $directTransactionLabels, $awaitRefresh);
            return;
        }

        $srcAccounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $src);
        $destAccounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $dest);

        $src1 = $srcAccounts[0]; // must have at least one because it was lazily created
        $dest1 = $destAccounts[0]; // must have at least one because it was lazily created

        if ($srcDeduction > $destAddition) {
            $amount1 = $destAddition;
            $amount2 = $srcDeduction - $destAddition;

            $src2 = $src1;
            $dest2 = yield from $this->getOracle($oracleName);
        } else { // $destAddition > $srcDeduction
            $amount1 = $srcDeduction;
            $amount2 = $destAddition - $srcDeduction;

            $src2 = yield from $this->getOracle($oracleName);
            $dest2 = $dest1;
        }

        $uuid1 = Uuid::uuid4();
        $uuid2 = Uuid::uuid4();
        $auxLabel = new LabelSet([TransactionLabels::UNEQUAL_AUX => $uuid1->toString()]);

        yield from $this->transact2(
            src1: $src1, dest1: $dest1, amount1: $amount1, labels1: $directTransactionLabels,
            src2: $src2, dest2: $dest2, amount2: $amount2, labels2: $oracleTransactionLabels->and($auxLabel),
            involvedPlayers: [$src, $dest], awaitRefresh: $awaitRefresh,
            uuid1: $uuid1, uuid2: $uuid2,
        );
    }

    /**
     * @return VoidPromise
     */
    public function addMoney(
        string $oracleName,
        Player $player,
        Schema\Complete $schema,
        int $amount,
        LabelSet $transactionLabels,
        bool $awaitRefresh = false,
    ) : Generator {
        $accounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $player);
        $account = $accounts[0]; // must have at least one because it was lazily created

        $oracle = yield from $this->getOracle($oracleName);
        yield from $this->transact($oracle, $account, $amount, $transactionLabels, [$player], $awaitRefresh);
    }

    /**
     * @return VoidPromise
     */
    public function takeMoney(
        string $oracleName,
        Player $player,
        Schema\Complete $schema,
        int $amount,
        LabelSet $transactionLabels,
        bool $awaitRefresh = false,
    ) : Generator {
        $accounts = yield from Schema\Utils::lazyCreate($schema, $this->database, $player);
        $account = $accounts[0]; // must have at least one because it was lazily created

        $oracle = yield from $this->getOracle($oracleName);
        yield from $this->transact($account, $oracle, $amount, $transactionLabels, [$player], $awaitRefresh);
    }
}
