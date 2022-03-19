<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use Generator;
use poggit\libasynql\DataConnector;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\result\SqlChangeResult;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlError;
use poggit\libasynql\SqlThread;
use Ramsey\Uuid\UuidInterface;
use SOFe\Capital\CapitalException;
use function count;

final class LabelManager {
    private DataConnector $conn;
    private string $dialect;

    /**
     * @param CapitalException::* $labelAlreadyExistsErrorCode
     * @param CapitalException::* $labelDoesNotExistErrorCode
     */
    public function __construct(
        Database $database,
        private string $labelTable,
        private int $labelAlreadyExistsErrorCode,
        private int $labelDoesNotExistErrorCode
    ) {
        $this->conn = $database->getDataConnector();
        $this->dialect = $database->dialect;
    }

    /**
     * @return VoidPromise
     * @throws CapitalException if the object already has this label
     */
    public function add(UuidInterface $id, string $name, string $value) : Generator {
        try {
            yield from QueryBuilder::new()
                ->addQuery("INSERT INTO {$this->labelTable} (id, name, value) VALUES (:id, :name, :value);", SqlThread::MODE_CHANGE)
                ->addParam("id", GenericVariable::TYPE_STRING, $id->toString())
                ->addParam("name", GenericVariable::TYPE_STRING, $name)
                ->addParam("value", GenericVariable::TYPE_STRING, $value)
                ->execute($this->conn, $this->dialect);
        } catch (SqlError $error) {
            throw new CapitalException($this->labelAlreadyExistsErrorCode, $error);
        }
    }

    /**
     * Updates a label.
     *
     * @return VoidPromise
     * @throws CapitalException if the object does not have this label
     */
    public function update(UuidInterface $id, string $name, string $value) : Generator {
        /** @var SqlChangeResult $result */
        [$result] = yield from QueryBuilder::new()
            ->addQuery("UPDATE {$this->labelTable} SET value = :value WHERE id = :id AND name = :name;", SqlThread::MODE_CHANGE)
            ->addParam("id", GenericVariable::TYPE_STRING, $id->toString())
            ->addParam("name", GenericVariable::TYPE_STRING, $name)
            ->addParam("value", GenericVariable::TYPE_STRING, $value)
            ->execute($this->conn, $this->dialect);
        if ($result->getAffectedRows() === 0) {
            throw new CapitalException($this->labelDoesNotExistErrorCode);
        }
    }

    /**
     * Adds or updates a label.
     *
     * @return VoidPromise
     */
    public function set(UuidInterface $id, string $name, string $value) : Generator {
        yield from QueryBuilder::new()
            ->addQuery("INSERT OR REPLACE INTO {$this->labelTable} (id, name, value) VALUES (:id, :name, :value);", SqlThread::MODE_CHANGE)
            ->addParam("id", GenericVariable::TYPE_STRING, $id->toString())
            ->addParam("name", GenericVariable::TYPE_STRING, $name)
            ->addParam("value", GenericVariable::TYPE_STRING, $value)
            ->execute($this->conn, $this->dialect);
    }

    /**
     * @return Generator<mixed, mixed, mixed, string>
     * @throws CapitalException if the object does not have this label
     */
    public function get(UuidInterface $id, string $name) : Generator {
        /** @var SqlSelectResult $result */
        [$result] = yield from QueryBuilder::new()
            ->addQuery("SELECT value FROM {$this->labelTable} WHERE id = :id AND name = :name;", SqlThread::MODE_SELECT)
            ->addParam("id", GenericVariable::TYPE_STRING, $id->toString())
            ->addParam("name", GenericVariable::TYPE_STRING, $name)
            ->execute($this->conn, $this->dialect);
        $rows = $result->getRows();
        if (count($rows) > 0) {
            return $rows[0]["value"];
        }
        throw new CapitalException($this->labelDoesNotExistErrorCode);
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<string, string>>
     */
    public function getAll(UuidInterface $id) : Generator {
        /** @var SqlSelectResult $result */
        [$result] = yield from QueryBuilder::new()
            ->addQuery("SELECT name, value FROM {$this->labelTable} WHERE id = :id", SqlThread::MODE_SELECT)
            ->addParam("id", GenericVariable::TYPE_STRING, $id->toString())
            ->execute($this->conn, $this->dialect);
        $output = [];
        foreach ($result->getRows() as $row) {
            /** @var string $name */
            $name = $row["name"];

            /** @var string $value */
            $value = $row["value"];

            $output[$name] = $value;
        }

        return $output;
    }

    /**
     * @param UuidInterface[] $ids
     * @return Generator<mixed, mixed, mixed, array<string, string>[]>
     */
    public function getAllForList(array $ids) : Generator {
        if (count($ids) === 0) {
            return [];
        }

        $flip = [];
        foreach ($ids as $k => $id) {
            $flip[$id->toString()] = $k;
        }

        $query = QueryBuilder::new();

        $i = 0;
        $queryString = "SELECT id, name, value FROM {$this->labelTable} WHERE id IN (";
        foreach ($flip as $id => $_) {
            $query->addParam("id{$i}", GenericVariable::TYPE_STRING, $id);

            if ($i > 0) {
                $queryString .= ", ";
            }
            $queryString .= ":id{$i}";

            $i += 1;
        }
        $queryString .= ")";

        /** @var SqlSelectResult $result */
        [$result] = yield from $query->addQuery($queryString, SqlThread::MODE_SELECT)->execute($this->conn, $this->dialect);

        $output = [];
        foreach ($result->getRows() as $row) {
            /** @var string $id */
            $id = $row["id"];
            /** @var string $name */
            $name = $row["name"];
            /** @var string $value */
            $value = $row["value"];

            $key = $flip[$id];
            if (!isset($output[$key])) {
                $output[$key] = [];
            }

            $output[$key][$name] = $value;
        }

        return $output;
    }
}
