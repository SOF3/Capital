<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use function array_keys;
use function array_map;
use function count;
use function implode;
use Generator;
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
use SOFe\Capital\CapitalException;
use SOFe\Capital\Config\Config;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\MainClass;
use SOFe\Capital\Singleton;
use SOFe\Capital\SingletonTrait;

final class Database implements Singleton {
    use SingletonTrait;

    private const SQL_FILES = [
        "init.sql",
        "account.sql",
        "transaction.sql",
    ];

    /** @var SqlDialect::SQLITE|SqlDialect::MYSQL */
    private string $dialect;
    private DataConnector $conn;
    private RawQueries $raw;

    public function __construct(MainClass $plugin, Config $config) {
        $dbConfig = $config->database;

        $this->conn = libasynql::create($plugin, $dbConfig->libasynql, [
            "sqlite" => array_map(fn($file) => "sqlite/$file", self::SQL_FILES),
            "mysql" => array_map(fn($file) => "mysql/$file", self::SQL_FILES),
        ]);

        if($dbConfig->logQueries) {
            $logger = new PrefixedLogger($plugin->getLogger(), "Database");
            $this->conn->setLogger($logger);
        }

        $this->raw = new RawQueries($this->conn);
        $this->dialect = match($dbConfig->libasynql["type"]) {
            "sqlite" => SqlDialect::SQLITE,
            "mysql" => SqlDialect::MYSQL,
            default => throw new RuntimeException("Unsupported SQL dialect " . $dbConfig->libasynql["type"]),
        };
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
        yield from $this->raw->initSqlite();
    }

    /**
     * @return VoidPromise
     */
    private function mysqlInit() : Generator {
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

    /**
     * @return Generator<mixed, mixed, mixed, list<UuidInterface>>
     */
    public function findAccountN(LabelSelector $selector) : Generator {
        $entries = $selector->getEntries();

        $query = "SELECT id FROM acc_label t0 ";
        for($i = 1; $i < count($entries); $i++) {
            $query .= "INNER JOIN acc_label t{$i} USING (id) ";
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

        $stmt = GenericStatementImpl::forDialect($this->dialect, "dynamic-find-account-n", [$query], "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match($this->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->conn->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_SELECT], yield Await::RESOLVE, yield Await::REJECT);
        /** @var SqlSelectResult $result */
        [$result] = yield Await::ONCE;

        $ids = [];
        foreach($result->getRows() as $row) {
            $ids[] = Uuid::fromString($row["id"]);
        }
        return $ids;
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

        $rows = yield from $this->raw->transactionCreate($uuid->toString(), $src->toString(), $dest->toString(), $amount, $srcMin, $destMax);
        $errno = $rows[0]["status"];

        match($errno) {
            0 => null,
            1 => throw new CapitalException(CapitalException::SOURCE_UNDERFLOW),
            2 => throw new CapitalException(CapitalException::DESTINATION_OVERFLOW),
            default => throw new RuntimeException("Transaction procedure returned unknown error code $errno"),
        };

        return $uuid;
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

        $srcMin = [PHP_INT_MIN, PHP_INT_MIN];
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

        return $uuid;
    }
}
