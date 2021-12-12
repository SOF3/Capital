<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use function array_keys;
use function array_map;
use function count;
use function implode;
use Generator;
use poggit\libasynql\DataConnector;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlDialect;
use poggit\libasynql\SqlError;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\CapitalException;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\MainClass;

final class Database {
    private static ?self $instance = null;

    private const SQL_FILES = [
        "init.sql",
        "account.sql",
        "transaction.sql",
    ];

    public static function getInstance() : self {
        return self::$instance ?? self::$instance = new self;
    }

    /** @var SqlDialect::SQLITE|SqlDialect::MYSQL */
    private string $dialect;
    private DataConnector $conn;
    private RawQueries $raw;

    public function __construct() {
        $plugin = MainClass::getInstance();

        $config = $plugin->getConfig()->get("database");

        $this->conn = libasynql::create($plugin, $config, [
            "sqlite" => array_map(fn($file) => "sqlite/$file", self::SQL_FILES),
            "mysql" => array_map(fn($file) => "mysql/$file", self::SQL_FILES),
        ]);

        $this->raw = new RawQueries($this->conn);
        $this->dialect = match($config["type"]) {
            "sqlite" => SqlDialect::SQLITE,
            "mysql" => SqlDialect::MYSQL,
            default => throw new RuntimeException("Unsupported SQL dialect " . $config["type"]),
        };
    }

    /**
     * @return VoidPromise
     */
    public function init() : Generator {
        yield from $this->raw->init();
    }


    // Accounts

    /**
     * @param array<string, string> $labels
     * @return Generator<mixed, mixed, mixed, UuidInterface>
     */
    public function createAccount(int $value, array $labels) : Generator {
        $uuid = Uuid::uuid4();
        yield from $this->raw->accountCreate($value, $uuid->toString());

        $promises = [];
        foreach($labels as $name => $value) {
            $promises[] = $this->addAccountLabel($uuid, $name, $value);
        }
        yield from Await::all($promises);
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
            throw new CapitalException(CapitalException::ACCOUNT_LABEL_ALREADY_EXISTS);
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

        $stmt = GenericStatementImpl::forDialect($this->dialect, "dynamic-find-account-n", $query, "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match($this->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->conn->executeSelectRaw($rawQuery, $rawArgs, yield Await::RESOLVE, yield Await::REJECT);
        $rows = yield Await::ONCE;

        $ids = [];
        foreach($rows as $row) {
            $ids[] = Uuid::fromString($row["id"]);
        }
        return $ids;
    }


    // Transactions

    /**
     * @return Generator<mixed, mixed, mixed, UuidInterface>
     * @throws CapitalException if the transaction failed
     */
    public function doTransaction(UuidInterface $src, UuidInterface $dest, int $amount) {
        $uuid = Uuid::uuid4();

        $srcMin = yield from $this->getAccountLabel($src, AccountLabels::VALUE_MIN);
        $srcMin = (int) $srcMin;

        $destMax = yield from $this->getAccountLabel($dest, AccountLabels::VALUE_MAX);
        $destMax = (int) $destMax;

        $rows = yield from $this->raw->transactionCreate($destMax, $srcMin, $amount, $dest->toString(), $src->toString(), $uuid->toString());
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
