<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Closure;
use Generator;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use function array_map;
use function count;

final class Capital {
    /**
     * @param array<string, string> $labels
     * @param Closure(string $transactionId):void $onComplete
     * @param Closure(CapitalException):void $onError
     */
    public static function doTransaction(
        AccountRef $src, AccountRef $dest, int $amount, array $labels,
        ?Closure $onComplete = null,
        ?Closure $onError = null,
    ) : void {
        $catches = [];
        if($onError !== null) {
            $catches[CapitalException::class] = $onError;
        }

        Await::g2c(self::awaitTransaction($src, $dest, $amount, $labels), $onComplete, $catches);
    }

    /**
     * @param array<string, string> $labels
     * @return Generator<mixed, mixed, mixed, TransactionRef> the transaction ID
     */
    public static function awaitTransaction(AccountRef $src, AccountRef $dest, int $amount, array $labels) : Generator {
        $event = new TransactionEvent($src, $dest, $amount, $labels);
        $event->call();

        $id = yield from Database::getInstance()->doTransaction($src->getId(), $dest->getId(), $amount);
        return new TransactionRef($id);
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<AccountRef>>
     */
    public static function findAccounts(LabelSelector $selector) : Generator {
        $accounts = yield from Database::getInstance()->findAccountN($selector);

        return array_map(fn($account) => new AccountRef($account), $accounts);
    }

    /**
     * @return Generator<mixed, mixed, mixed, AccountRef>
     */
    public static function getOracle(string $name) : Generator {
        $labels = [
            AccountLabels::ORACLE => $name,
        ];

        $accounts = yield from self::findAccounts(new LabelSelector($labels));
        if(count($accounts) > 0) {
            return $accounts[0];
        }

        // Do not apply valueMin and valueMax on this account,
        // otherwise we will get failing transactions and it's no longer an oracle.
        $account = yield from Database::getInstance()->createAccount(0, $labels);
        return new AccountRef($account);
    }
}
