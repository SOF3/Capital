<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use Generator;
use Logger;
use poggit\libasynql\DataConnector;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\libasynql;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlDialect;
use poggit\libasynql\SqlError;
use poggit\libasynql\SqlThread;
use PrefixedLogger;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\AccountQueryMetric;
use SOFe\Capital\CapitalException;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\MainClass;
use SOFe\Capital\TransactionQueryMetric;
use SOFe\RwLock\Mutex;
use function array_keys;
use function array_map;
use function array_search;
use function count;
use function implode;
use function max;
use function min;
use function preg_match;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class Database implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    private const SQL_FILES = [
        "init.sql",
        "account.sql",
        "transaction.sql",
    ];

    /** @var SqlDialect::SQLITE|SqlDialect::MYSQL */
    private string $dialect;
    private DataConnector $conn;
    private RawQueries $raw;

    private Mutex $sqliteMutex;

    public function __construct(private Logger $logger, MainClass $plugin, Config $config) {
        $this->dialect = match($config->libasynql["type"]) {
            "sqlite" => SqlDialect::SQLITE,
            "mysql" => SqlDialect::MYSQL,
            default => throw new RuntimeException("Unsupported SQL dialect " . $config->libasynql["type"]),
        };

        if($this->dialect === SqlDialect::SQLITE && $config->libasynql["worker-limit"] !== 1) {
            $this->logger->warning("Multi-worker is not supported for SQLite databases. Force setting worker-limit to 1.");
            $config->libasynql["worker-limit"] = 1;
        }

        if($this->dialect === SqlDialect::SQLITE) {
            $this->sqliteMutex = new Mutex;
        }

        $this->conn = libasynql::create($plugin, $config->libasynql, [
            "sqlite" => array_map(fn($file) => "sqlite/$file", self::SQL_FILES),
            "mysql" => array_map(fn($file) => "mysql/$file", self::SQL_FILES),
        ]);

        if($config->logQueries) {
            $this->conn->setLogger($this->logger);
        }

        $this->raw = new RawQueries($this->conn);
    }

    /**
     * @return VoidPromise
     */
    public function init() : Generator {
        yield from match($this->dialect) {
            SqlDialect::SQLITE => $this->sqliteInit(),
            SqlDialect::MYSQL => $this->mysqlInit(),
        };
    }

    /**
     * @return VoidPromise
     */
    private function sqliteInit() : Generator {
        $this->logger->debug("Initializing SQlite database");
        yield from $this->raw->initSqlite();
    }

    /**
     * @return VoidPromise
     */
    private function mysqlInit() : Generator {
        $this->logger->debug("Initializing MySQL database");

        yield from $this->raw->initMysqlTables();

        yield from $this->tryCreateProcedure($this->raw->initMysqlProceduresTranCreate());
        yield from $this->tryCreateProcedure($this->raw->initMysqlProceduresTranCreate2());
    }

    /**
     * @param Generator<mixed, mixed, mixed, int> $generator
     * @return VoidPromise
     */
    private function tryCreateProcedure(Generator $generator) : Generator {
        try {
            yield from $generator;
        } catch(SqlError $error) {
            if(preg_match('/^procedure [^ ]+ already exists$/i', $error->getErrorMessage())) {
                // ignore
            } else {
                throw $error;
            }
        }
    }

    public function shutdown() : void {
        $this->conn->close();
    }


    // Accounts

    /**
     * @param array<string, string> $labels
     * @return Generator<mixed, mixed, mixed, UuidInterface>
     */
    public function createAccount(int $value, array $labels) : Generator {
        $uuid = Uuid::uuid4();
        yield from $this->raw->accountCreate($uuid->toString(), $value);

        $promises = [];
        foreach($labels as $name => $value) {
            $promises[] = $this->addAccountLabel($uuid, $name, $value);
        }
        if(count($promises) > 0) {
            yield from Await::all($promises);
        }
        return $uuid;
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     * @throws CapitalException if the account does not exist
     */
    public function getAccountValue(UuidInterface $id) : Generator {
        $rows = yield from $this->raw->accountFetch($id->toString());
        if(count($rows) > 0) {
            return $rows[0]["value"];
        }
        throw new CapitalException(CapitalException::NO_SUCH_ACCOUNT);
    }

    /**
     * @param UuidInterface[] $ids
     * @return Generator<mixed, mixed, mixed, int[]> null
     * @throws CapitalException if any of the accounts does not exist
     */
    public function getAccountListValues(array $ids) : Generator {
        if(count($ids) === 0) {
            return [];
        }

        $flip = [];
        foreach($ids as $k => $id) {
            $flip[$id->toString()] = $k;
        }

        $rows = yield from $this->raw->accountFetchList(array_keys($flip));

        if(count($rows) !== count($ids)) {
            throw new CapitalException(CapitalException::NO_SUCH_ACCOUNT);
        }

        $output = [];

        foreach($rows as $row) {
            $output[$flip[$row["id"]]] = $row["value"];
        }

        return $output;
    }


    // Account labels

    /**
     * @return VoidPromise
     * @throws CapitalException if the account already has this label
     */
    public function addAccountLabel(UuidInterface $id, string $name, string $value) : Generator {
        try {
            yield from $this->raw->accountLabelAdd($id->toString(), $name, $value);
        } catch(SqlError $error) {
            throw new CapitalException(CapitalException::ACCOUNT_LABEL_ALREADY_EXISTS, $error);
        }
    }

    /**
     * @return VoidPromise
     * @throws CapitalException if the account does not have this label
     */
    public function updateAccountLabel(UuidInterface $id, string $name, string $value) : Generator {
        $changes = yield from $this->raw->accountLabelUpdate($id->toString(), $name, $value);
        if($changes === 0) {
            throw new CapitalException(CapitalException::ACCOUNT_LABEL_DOES_NOT_EXIST);
        }
    }

    /**
     * @return VoidPromise
     */
    public function setAccountLabel(UuidInterface $id, string $name, string $value) : Generator {
        yield from $this->raw->accountLabelAddOrUpdate($id->toString(), $name, $value);
    }

    /**
     * @return Generator<mixed, mixed, mixed, string>
     * @throws CapitalException if the account does not have this label
     */
    public function getAccountLabel(UuidInterface $id, string $name) : Generator {
        $rows = yield from $this->raw->accountLabelFetch($id->toString(), $name);
        if(count($rows) > 0) {
            return $rows[0]["value"];
        }
        throw new CapitalException(CapitalException::ACCOUNT_LABEL_DOES_NOT_EXIST);
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<string, string>>
     */
    public function getAccountAllLabels(UuidInterface $id) : Generator {
        $rows = yield from $this->raw->accountLabelFetchAll($id->toString());
        $result = [];
        foreach($rows as $row) {
            /** @var string $name */
            $name = $row["name"];

            /** @var string $value */
            $value = $row["value"];

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * @param UuidInterface[] $ids
     * @return Generator<mixed, mixed, mixed, array<string, string>[]>
     */
    public function getAccountListAllLabels(array $ids) : Generator {
        if(count($ids) === 0) {
            return [];
        }

        $flip = [];
        foreach($ids as $k => $id) {
            $flip[$id->toString()] = $k;
        }

        $rows = yield from $this->raw->accountLabelFetchAllMulti(array_keys($flip));

        $output = [];
        foreach($rows as $row) {
            /** @var string $id */
            $id = $row["id"];
            /** @var string $name */
            $name = $row["name"];
            /** @var string $value */
            $value = $row["value"];

            $key = $flip[$id];
            if(!isset($output[$key])) {
                $output[$key] = [];
            }

            $output[$key][$name] = $value;
        }

        return $output;
    }


    // Dynamic account label queries

    /**
     * @param array<AccountQueryMetric> $metrics
     * @return Generator<mixed, mixed, mixed, array<int|float>>
     */
    public function aggregateAccounts(LabelSelector $selector, array $metrics) : Generator {
        $columns = [];

        $i = 0;
        $joinMain = false;
        foreach($metrics as $metric) {
            $columns[] = $metric->getExpr() . " AS metric_{$i}";
            $i++;

            if(!$metric->usesIdOnly()) {
                $joinMain = true;
            }
        }

        $result = yield from $this->queryAccounts(
            selector: $selector,
            columns: implode(", ", $columns),
            groupBy: [],
            orderBy: null,
            descending: false,
            limit: null,
            joinMain: $joinMain,
        );
        [$row] = $result->getRows();

        $output = [];
        $i = 0;
        foreach($metrics as $key => $metric) {
            $output[$key] = $row["metric_{$i}"];
            $i++;
        }
        return $output;
    }

    /**
     * @param list<string> $groupBy
     * @param array<AccountQueryMetric> $otherMetrics
     * @return Generator<mixed, mixed, mixed, array<AggregateTopEntry>>
     */
    public function aggregateTopAccounts(LabelSelector $selector, array $groupBy, AccountQueryMetric $orderingMetric, bool $descending, string $orderingMetricName, array $otherMetrics, int $limit) : Generator {
        $columns = $orderingMetric->getExpr() . " AS metric_ordering";

        $i = 0;
        $joinMain = !$orderingMetric->usesIdOnly();
        foreach($otherMetrics as $metric) {
            $columns .= ", " . $metric->getExpr() . " AS metric_{$i}";
            $i++;

            if(!$metric->usesIdOnly()) {
                $joinMain = true;
            }
        }

        foreach($groupBy as $i => $_) {
            $columns .= ", t{$i}.value AS group_{$i}";
        }

        $newSelector = [];
        foreach($groupBy as $label) {
            $newSelector[$label] = LabelSelector::ANY_VALUE;
        }

        foreach($selector->getEntries() as $label => $value) {
            if(isset($newSelector[$label])) {
                throw new RuntimeException("groupBy labels must not be used in the label selector");
            }

            $newSelector[$label] = $value;
        }

        $result = yield from $this->queryAccounts(
            selector: new LabelSelector($newSelector),
            columns: $columns,
            groupBy: $groupBy,
            orderBy: "metric_ordering",
            descending: $descending,
            limit: $limit,
            joinMain: $joinMain,
        );

        $output = [];
        foreach($result->getRows() as $dbRow) {
            $outputGroupValues = [];
            foreach($groupBy as $i => $_) {
                $outputGroupValues[] = $dbRow["group_{$i}"];
            }

            $outputMetrics[$orderingMetricName] = $dbRow["metric_ordering"];

            $i = 0;
            foreach($otherMetrics as $key => $metric) {
                $outputMetrics[$key] = $dbRow["metric_{$i}"];
                $i++;
            }

            $output[] = new AggregateTopEntry($outputGroupValues, $outputMetrics);
        }

        return $output;
    }

    /**
     * @return Generator<mixed, mixed, mixed, list<UuidInterface>>
     */
    public function findAccounts(LabelSelector $selector) : Generator {
        $result = yield from $this->queryAccounts(
            selector: $selector,
            columns: "id",
            groupBy: [],
            orderBy: null,
            descending: false,
            limit: null,
            joinMain: false,
        );

        $ids = [];
        foreach($result->getRows() as $row) {
            $ids[] = Uuid::fromString($row["id"]);
        }
        return $ids;
    }

    /**
     * @param list<string> $groupBy
     * @return Generator<mixed, mixed, mixed, SqlSelectResult>
     */
    private function queryAccounts(
        LabelSelector $selector,
        string $columns,
        array $groupBy,
        ?string $orderBy,
        bool $descending,
        ?int $limit,
        bool $joinMain,
    ) : Generator {
        $entries = $selector->getEntries();

        $query = "SELECT {$columns} FROM acc_label AS t0 ";
        for($i = 1; $i < count($entries); $i++) {
            $query .= "INNER JOIN acc_label AS t{$i} USING (id) ";
        }

        if($joinMain) {
            $query .= "INNER JOIN acc USING (id) ";
        }

        $query .= "WHERE ";

        $vars = [];
        $args = [];

        $conditions = [];
        $i = 0;
        foreach($entries as $name => $value) {
            $condition = "t{$i}.name = :name{$i}";
            $vars["name{$i}"] = new GenericVariable("name{$i}", GenericVariable::TYPE_STRING, null);
            $args["name{$i}"] = $name;

            if($value !== LabelSelector::ANY_VALUE) {
                $condition .= " AND t{$i}.value = :value{$i}";
                $vars["value{$i}"] = new GenericVariable("value{$i}", GenericVariable::TYPE_STRING, null);
                $args["value{$i}"] = $value;
            }

            $conditions[] = $condition;
            $i++;
        }
        $query .= implode(" AND ", $conditions);

        if($groupBy !== []) {
            $groupByList = [];
            foreach($groupBy as $label) {
                $groupById = array_search($label, array_keys($entries), true);
                if($groupById === false) {
                    throw new RuntimeException("Cannot group by $label because it is not in selector");
                }

                $groupByList[] = "t{$groupById}.value";
            }

            $query .= " GROUP BY " . implode(", ", $groupByList);
        }

        if($orderBy !== null) {
            $query .= " ORDER BY `{$orderBy}`";
            if($descending) {
                $query .= " DESC";
            }
        }

        if($limit !== null) {
            $query .= " LIMIT {$limit}";
        }

        $stmt = GenericStatementImpl::forDialect($this->dialect, "dynamic-find-accounts", [$query], "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match($this->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->conn->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_SELECT], yield Await::RESOLVE, yield Await::REJECT);
        /** @var SqlSelectResult $result */
        [$result] = yield Await::ONCE;
        return $result;
    }


    // Transactions

    /**
     * @return Generator<mixed, mixed, mixed, UuidInterface>
     * @throws CapitalException if the transaction failed
     */
    public function doTransaction(
        UuidInterface $src,
        UuidInterface $dest,
        int $amount,
        ?string $srcMinLabel = AccountLabels::VALUE_MIN,
        ?string $destMaxLabel = AccountLabels::VALUE_MAX,
    ) : Generator {
        $uuid = Uuid::uuid4();

        $srcMin = PHP_INT_MIN;
        if($srcMinLabel !== null) {
            try {
                $srcMinString = yield from $this->getAccountLabel($src, $srcMinLabel);
                $srcMin = (int) $srcMinString;
            } catch(CapitalException $ex) {
                if($ex->getCode() !== CapitalException::ACCOUNT_LABEL_DOES_NOT_EXIST) {
                    throw $ex;
                }
                // else, we don't need a constraint
            }
        }

        $destMax = PHP_INT_MAX;
        if($destMaxLabel !== null) {
            try {
                $destMaxString = yield from $this->getAccountLabel($dest, $destMaxLabel);
                $destMax = (int) $destMaxString;
            } catch(CapitalException $ex) {
                if($ex->getCode() !== CapitalException::ACCOUNT_LABEL_DOES_NOT_EXIST) {
                    throw $ex;
                }
                // else, we don't need a constraint
            }
        } else {
        }

        $destMax = yield from $this->getAccountLabel($dest, AccountLabels::VALUE_MAX);
        $destMax = (int) $destMax;

        yield from match($this->dialect) {
            SqlDialect::SQLITE => $this->sqliteMutex->run($this->doTransactionSqlite($uuid, $src, $dest, $amount, $srcMin, $destMax)),
            SqlDialect::MYSQL => $this->doTransactionMysql($uuid, $src, $dest, $amount, $srcMin, $destMax),
        };

        return $uuid;
    }

    /**
     * @return VoidPromise
     */
    private function doTransactionSqlite(UuidInterface $uuid, UuidInterface $src, UuidInterface $dest, int $amount, int $srcMin, int $destMax) : Generator {
        $srcCheck = function() use($src, $amount, $srcMin) {
            $srcValue = yield from $this->getAccountValue($src);
            if($srcValue - $amount < $srcMin) {
                throw new CapitalException(CapitalException::SOURCE_UNDERFLOW);
            }
        };

        $destCheck = function() use($dest, $amount, $destMax) {
            $destValue = yield from $this->getAccountValue($dest);
            if($destValue - $amount > $destMax) {
                throw new CapitalException(CapitalException::SOURCE_UNDERFLOW);
            }
        };

        yield from Await::all([$srcCheck(), $destCheck()]);

        yield from Await::all([
            $this->raw->transactionInsert($uuid->toString(), $src->toString(), $dest->toString(), $amount),
            $this->raw->accountSqliteUnsafeDelta($src->toString(), -$amount),
            $this->raw->accountSqliteUnsafeDelta($dest->toString(), $amount),
        ]);
    }

    /**
     * @return VoidPromise
     */
    private function doTransactionMysql(UuidInterface $uuid, UuidInterface $src, UuidInterface $dest, int $amount, int $srcMin, int $destMax) : Generator {
        $rows = yield from $this->raw->transactionCreate($uuid->toString(), $src->toString(), $dest->toString(), $amount, $srcMin, $destMax);
        $errno = $rows[0]["status"];

        match($errno) {
            0 => null,
            1 => throw new CapitalException(CapitalException::SOURCE_UNDERFLOW),
            2 => throw new CapitalException(CapitalException::DESTINATION_OVERFLOW),
            default => throw new RuntimeException("Transaction procedure returned unknown error code $errno"),
        };
    }

    /**
     * @return Generator<mixed, mixed, mixed, array{UuidInterface, UuidInterface}>
     * @throws CapitalException if the transaction failed
     */
    public function doTransaction2(
        UuidInterface $src1,
        UuidInterface $dest1,
        int $amount1,
        UuidInterface $src2,
        UuidInterface $dest2,
        int $amount2,
        ?string $srcMinLabel = AccountLabels::VALUE_MIN,
        ?string $destMaxLabel = AccountLabels::VALUE_MAX,
        ?UuidInterface $uuid1 = null,
        ?UuidInterface $uuid2 = null,
    ) : Generator {
        $uuid = [$uuid1 ?? Uuid::uuid4(), $uuid2 ?? Uuid::uuid4()];

        $src = [$src1, $src2];
        $dest = [$dest1, $dest2];
        $amount = [$amount1, $amount2];

        /** @var array{int, int} $srcMin */
        $srcMin = [PHP_INT_MIN, PHP_INT_MIN];
        /** @var array{int, int} $destMax */
        $destMax = [PHP_INT_MAX, PHP_INT_MAX];

        for($i = 0; $i < 2; $i++) {
            if($srcMinLabel !== null) {
                try {
                    $srcMinString = yield from $this->getAccountLabel($src[$i], $srcMinLabel);
                    $srcMin[$i] = (int) $srcMinString;
                } catch(CapitalException $ex) {
                    if($ex->getCode() !== CapitalException::ACCOUNT_LABEL_DOES_NOT_EXIST) {
                        throw $ex;
                    }
                    // else, we don't need a constraint
                }
            }

            if($destMaxLabel !== null) {
                try {
                    $destMaxString = yield from $this->getAccountLabel($dest[$i], $destMaxLabel);
                    $destMax[$i] = (int) $destMaxString;
                } catch(CapitalException $ex) {
                    if($ex->getCode() !== CapitalException::ACCOUNT_LABEL_DOES_NOT_EXIST) {
                        throw $ex;
                    }
                    // else, we don't need a constraint
                }
            }
        }

        /** @var array{int, int} $srcMin */
        /** @var array{int, int} $destMax */

        yield from match($this->dialect) {
            SqlDialect::SQLITE => $this->sqliteMutex->run($this->doTransaction2Sqlite($uuid, $src, $dest, $amount, $srcMin, $destMax)),
            SqlDialect::MYSQL => $this->doTransaction2Mysql($uuid, $src, $dest, $amount, $srcMin, $destMax),
        };

        return $uuid;
    }

    /**
     * @param array{UuidInterface, UuidInterface} $uuid
     * @param array{UuidInterface, UuidInterface} $src
     * @param array{UuidInterface, UuidInterface} $dest
     * @param array{int, int} $amount
     * @param array{int, int} $srcMin
     * @param array{int, int} $destMax
     * @return VoidPromise
     */
    private function doTransaction2Sqlite(array $uuid, array $src, array $dest, array $amount, array $srcMin, array $destMax) : Generator {
        /** @var array<string, int> $deltas the value stores the changes of the key (account binary UUID) after this transaction */
        $deltas = [];
        /** @var array<string, int> $minMap the value stores the allowed minimum value of the key (account binary UUID), PHP_INT_MIN if unbounded */
        $minMap = [];
        /** @var array<string, int> $maxMap the value stores the allowed maximum value of the key (account binary UUID), PHP_INT_MAX if unbounded */
        $maxMap = [];

        for($i = 0; $i < 2; $i++) {
            $srcKey = $src[$i]->getBytes();
            $destKey = $dest[$i]->getBytes();

            $deltas[$srcKey] = ($deltas[$srcKey] ?? 0) - $amount[$i];
            $deltas[$destKey] = ($deltas[$destKey] ?? 0) + $amount[$i];

            foreach([
                [$srcKey, $srcMin[$i], PHP_INT_MAX],
                [$destKey, PHP_INT_MIN, $destMax[$i]],
            ] as [$key, $min, $max]) {
                $minMap[$key] = max($minMap[$key] ?? $min, $min);
                $maxMap[$key] = min($maxMap[$key] ?? $max, $max);
            }
        }

        /** @var array<int, int> For $k => $v, array_keys($deltas)[$k] is the account binary UUID, and $v is the current value of the account before transactions */
        $values = yield from $this->getAccountListValues(array_map(fn($id) => Uuid::fromBytes($id), array_keys($deltas)));
        foreach(array_keys($deltas) as $j => $key) {
            $value = $values[$j];
            $delta = $deltas[$key];
            $min = $minMap[$key];
            $max = $maxMap[$key];

            if($value + $delta < $min) {
                throw new CapitalException(CapitalException::SOURCE_UNDERFLOW);
            }

            if($value + $delta > $max) {
                throw new CapitalException(CapitalException::DESTINATION_OVERFLOW);
            }
        }

        $promises = [];
        for($i = 0; $i < 2; $i++) {
            $promises[] = $this->raw->transactionInsert($uuid[$i]->toString(), $src[$i]->toString(), $dest[$i]->toString(), $amount[$i]);
        }
        foreach(array_keys($deltas) as $j => $key) {
            $uuid = Uuid::fromBytes($key);
            $promises[] = $this->raw->accountSqliteUnsafeDelta($uuid->toString(), $deltas[$key]);
        }
        yield from Await::all($promises);
    }

    /**
     * @param array{UuidInterface, UuidInterface} $uuid
     * @param array{UuidInterface, UuidInterface} $src
     * @param array{UuidInterface, UuidInterface} $dest
     * @param array{int, int} $amount
     * @param array{int, int} $srcMin
     * @param array{int, int} $destMax
     * @return VoidPromise
     */
    private function doTransaction2Mysql(array $uuid, array $src, array $dest, array $amount, array $srcMin, array $destMax) : Generator {
        $rows = yield from $this->raw->transactionCreate2(
            $uuid[0]->toString(), $src[0]->toString(), $dest[0]->toString(), $amount[0], $srcMin[0], $destMax[0],
            $uuid[1]->toString(), $src[1]->toString(), $dest[1]->toString(), $amount[1], $srcMin[1], $destMax[1],
        );
        $errno = $rows[0]["status"];

        match($errno) {
            0 => null,
            1 => throw new CapitalException(CapitalException::SOURCE_UNDERFLOW),
            2 => throw new CapitalException(CapitalException::DESTINATION_OVERFLOW),
            default => throw new RuntimeException("Transaction procedure returned unknown error code $errno"),
        };
    }


    // Transaction labels

    /**
     * @return VoidPromise
     * @throws CapitalException if the transaction already has this label
     */
    public function addTransactionLabel(UuidInterface $id, string $name, string $value) : Generator {
        try {
            yield from $this->raw->transactionLabelAdd($id->toString(), $name, $value);
        } catch(SqlError $error) {
            throw new CapitalException(CapitalException::TRANSACTION_LABEL_ALREADY_EXISTS, $error);
        }
    }

    /**
     * @return VoidPromise
     * @throws CapitalException if the transaction does not have this label
     */
    public function updateTransactionLabel(UuidInterface $id, string $name, string $value) : Generator {
        $changes = yield from $this->raw->transactionLabelUpdate($id->toString(), $name, $value);
        if($changes === 0) {
            throw new CapitalException(CapitalException::TRANSACTION_LABEL_DOES_NOT_EXIST);
        }
    }

    /**
     * @return VoidPromise
     */
    public function setTransactionLabel(UuidInterface $id, string $name, string $value) : Generator {
        yield from $this->raw->transactionLabelAddOrUpdate($id->toString(), $name, $value);
    }

    /**
     * @return Generator<mixed, mixed, mixed, string>
     * @throws CapitalException if the transaction does not have this label
     */
    public function getTransactionLabel(UuidInterface $id, string $name) : Generator {
        $rows = yield from $this->raw->transactionLabelFetch($id->toString(), $name);
        if(count($rows) > 0) {
            return $rows[0]["value"];
        }
        throw new CapitalException(CapitalException::TRANSACTION_LABEL_DOES_NOT_EXIST);
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<string, string>>
     */
    public function getTransactionAllLabels(UuidInterface $id) : Generator {
        $rows = yield from $this->raw->transactionLabelFetchAll($id->toString());
        $result = [];
        foreach($rows as $row) {
            /** @var string $name */
            $name = $row["name"];

            /** @var string $value */
            $value = $row["value"];

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * @param UuidInterface[] $ids
     * @return Generator<mixed, mixed, mixed, array<string, string>[]>
     */
    public function getTransactionListAllLabels(array $ids) : Generator {
        if(count($ids) === 0) {
            return [];
        }

        $flip = [];
        foreach($ids as $k => $id) {
            $flip[$id->toString()] = $k;
        }

        $rows = yield from $this->raw->transactionLabelFetchAllMulti(array_keys($flip));

        $output = [];
        foreach($rows as $row) {
            /** @var string $id */
            $id = $row["id"];
            /** @var string $name */
            $name = $row["name"];
            /** @var string $value */
            $value = $row["value"];

            $key = $flip[$id];
            if(!isset($output[$key])) {
                $output[$key] = [];
            }

            $output[$key][$name] = $value;
        }

        return $output;
    }


    // Dynamic transaction label queries

    /**
     * @param array<TransactionQueryMetric> $metrics
     * @return Generator<mixed, mixed, mixed, array<int|float>>
     */
    public function aggregateTransactions(LabelSelector $selector, array $metrics) : Generator {
        $columns = [];

        $i = 0;
        $joinMain = false;
        foreach($metrics as $metric) {
            $columns[] = $metric->getExpr() . " AS metric_{$i}";
            $i++;

            if(!$metric->usesIdOnly()) {
                $joinMain = true;
            }
        }

        $result = yield from $this->queryTransactions(
            selector: $selector,
            columns: implode(", ", $columns),
            groupBy: [],
            orderBy: null,
            descending: false,
            limit: null,
            joinMain: $joinMain,
        );
        [$row] = $result->getRows();

        $output = [];
        $i = 0;
        foreach($metrics as $key => $metric) {
            $output[$key] = $row["metric_{$i}"];
            $i++;
        }
        return $output;
    }

    /**
     * @param list<string> $groupBy
     * @param array<TransactionQueryMetric> $otherMetrics
     * @return Generator<mixed, mixed, mixed, array<AggregateTopEntry>>
     */
    public function aggregateTopTransactions(LabelSelector $selector, array $groupBy, TransactionQueryMetric $orderingMetric, bool $descending, string $orderingMetricName, array $otherMetrics, int $limit) : Generator {
        $columns = $orderingMetric->getExpr() . " AS metric_ordering";

        $i = 0;
        $joinMain = !$orderingMetric->usesIdOnly();
        foreach($otherMetrics as $metric) {
            $columns .= ", " . $metric->getExpr() . " AS metric_{$i}";
            $i++;

            if(!$metric->usesIdOnly()) {
                $joinMain = true;
            }
        }

        foreach($groupBy as $i => $_) {
            $columns .= ", t{$i}.value AS group_{$i}";
        }

        $newSelector = [];
        foreach($groupBy as $label) {
            $newSelector[$label] = LabelSelector::ANY_VALUE;
        }

        foreach($selector->getEntries() as $label => $value) {
            if(isset($newSelector[$label])) {
                throw new RuntimeException("groupBy labels must not be used in the label selector");
            }

            $newSelector[$label] = $value;
        }

        $result = yield from $this->queryTransactions(
            selector: new LabelSelector($newSelector),
            columns: $columns,
            groupBy: $groupBy,
            orderBy: "metric_ordering",
            descending: $descending,
            limit: $limit,
            joinMain: $joinMain,
        );

        $output = [];
        foreach($result->getRows() as $dbRow) {
            $outputGroupValues = [];
            foreach($groupBy as $i => $_) {
                $outputGroupValues[] = $dbRow["group_{$i}"];
            }

            $outputMetrics[$orderingMetricName] = $dbRow["metric_ordering"];

            $i = 0;
            foreach($otherMetrics as $key => $metric) {
                $outputMetrics[$key] = $dbRow["metric_{$i}"];
                $i++;
            }

            $output[] = new AggregateTopEntry($outputGroupValues, $outputMetrics);
        }

        return $output;
    }

    /**
     * @return Generator<mixed, mixed, mixed, list<UuidInterface>>
     */
    public function findTransactions(LabelSelector $selector) : Generator {
        $result = yield from $this->queryTransactions(
            selector: $selector,
            columns: "id",
            groupBy: [],
            orderBy: null,
            descending: false,
            limit: null,
            joinMain: false,
        );

        $ids = [];
        foreach($result->getRows() as $row) {
            $ids[] = Uuid::fromString($row["id"]);
        }
        return $ids;
    }

    /**
     * @param list<string> $groupBy
     * @return Generator<mixed, mixed, mixed, SqlSelectResult>
     */
    private function queryTransactions(
        LabelSelector $selector,
        string $columns,
        array $groupBy,
        ?string $orderBy,
        bool $descending,
        ?int $limit,
        bool $joinMain,
    ) : Generator {
        $entries = $selector->getEntries();

        $query = "SELECT {$columns} FROM tran_label AS t0 ";
        for($i = 1; $i < count($entries); $i++) {
            $query .= "INNER JOIN tran_label AS t{$i} USING (id) ";
        }

        if($joinMain) {
            $query .= "INNER JOIN tran USING (id) ";
        }

        $query .= "WHERE ";

        $vars = [];
        $args = [];

        $conditions = [];
        $i = 0;
        foreach($entries as $name => $value) {
            $condition = "t{$i}.name = :name{$i}";
            $vars["name{$i}"] = new GenericVariable("name{$i}", GenericVariable::TYPE_STRING, null);
            $args["name{$i}"] = $name;

            if($value !== LabelSelector::ANY_VALUE) {
                $condition .= " AND t{$i}.value = :value{$i}";
                $vars["value{$i}"] = new GenericVariable("value{$i}", GenericVariable::TYPE_STRING, null);
                $args["value{$i}"] = $value;
            }

            $conditions[] = $condition;
            $i++;
        }
        $query .= implode(" AND ", $conditions);

        if($groupBy !== []) {
            $groupByList = [];
            foreach($groupBy as $label) {
                $groupById = array_search($label, array_keys($entries), true);
                if($groupById === false) {
                    throw new RuntimeException("Cannot group by $label because it is not in selector");
                }

                $groupByList[] = "t{$groupById}.value";
            }

            $query .= " GROUP BY " . implode(", ", $groupByList);
        }

        if($orderBy !== null) {
            $query .= " ORDER BY `{$orderBy}`";
            if($descending) {
                $query .= " DESC";
            }
        }

        if($limit !== null) {
            $query .= " LIMIT {$limit}";
        }

        $stmt = GenericStatementImpl::forDialect($this->dialect, "dynamic-find-transactions", [$query], "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match($this->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->conn->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_SELECT], yield Await::RESOLVE, yield Await::REJECT);
        /** @var SqlSelectResult $result */
        [$result] = yield Await::ONCE;
        return $result;
    }
}
