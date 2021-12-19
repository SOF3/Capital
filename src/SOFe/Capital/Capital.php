<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use function array_map;
use function count;

final class Capital {
    /**
     * @param array<string, string> $labels
     * @return Generator<mixed, mixed, mixed, TransactionRef> the transaction ID
     */
    public static function transact(AccountRef $src, AccountRef $dest, int $amount, array $labels) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $event = new TransactionEvent($src, $dest, $amount, $labels);
        $event->call();
        $amount = $event->getAmount();
        $labels = $event->getLabels();

        $id = yield from $db->doTransaction($src->getId(), $dest->getId(), $amount);

        $promises = [];
        foreach($labels as $labelName => $labelValue) {
            $promises[] = $db->setTransactionLabel($id, $labelName, $labelValue);
        }
        yield from Await::all($promises);

        return new TransactionRef($id);
    }

    /**
     * @param array<string, string> $labels1
     * @param array<string, string> $labels2
     * @return Generator<mixed, mixed, mixed, array{TransactionRef, TransactionRef}> the transaction IDs
     */
    public static function transact2(
        AccountRef $src1, AccountRef $dest1, int $amount1, array $labels1,
        AccountRef $src2, AccountRef $dest2, int $amount2, array $labels2,
        ?UuidInterface $uuid1 = null, ?UuidInterface $uuid2 = null,
    ) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $event = new TransactionEvent($src1, $dest1, $amount1, $labels1);
        $event->call();
        $amount1 = $event->getAmount();
        $labels1 = $event->getLabels();

        $event = new TransactionEvent($src2, $dest2, $amount2, $labels2);
        $event->call();
        $amount2 = $event->getAmount();
        $labels2 = $event->getLabels();

        $ids = yield from $db->doTransaction2(
            $src1->getId(), $dest1->getId(), $amount1,
            $src2->getId(), $dest2->getId(), $amount2,
            AccountLabels::VALUE_MIN, AccountLabels::VALUE_MAX,
            $uuid1, $uuid2,
        );

        $promises = [];
        foreach($labels1 as $labelName => $labelValue) {
            $promises[] = $db->setTransactionLabel($ids[0], $labelName, $labelValue);
        }
        foreach($labels2 as $labelName => $labelValue) {
            $promises[] = $db->setTransactionLabel($ids[1], $labelName, $labelValue);
        }
        yield from Await::all($promises);

        return [new TransactionRef($ids[0]), new TransactionRef($ids[1])];
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<AccountRef>>
     */
    public static function findAccounts(LabelSelector $selector) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $accounts = yield from $db->findAccounts($selector);

        return array_map(fn($account) => new AccountRef($account), $accounts);
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     */
    public static function getBalance(AccountRef $account) : Generator {
        $db = Database::get(MainClass::$typeMap);

        return yield from $db->getAccountValue($account->getId());
    }

    /**
     * @param array<AccountRef> $accounts
     * @return Generator<mixed, mixed, mixed, array<int>>
     */
    public static function getBalances(array $accounts) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $ids = [];
        foreach($accounts as $key => $account) {
            $ids[$key] = $account->getId();
        }

        return yield from $db->getAccountListValues($ids);
    }

    /**
     * @return Generator<mixed, mixed, mixed, AccountRef>
     */
    public static function getOracle(string $name) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $labels = [
            AccountLabels::ORACLE => $name,
        ];

        $accounts = yield from self::findAccounts(new LabelSelector($labels));
        if(count($accounts) > 0) {
            return $accounts[0];
        }

        // Do not apply valueMin and valueMax on this account,
        // otherwise we will get failing transactions and it's no longer an oracle.
        $account = yield from $db->createAccount(0, $labels);
        return new AccountRef($account);
    }
}
