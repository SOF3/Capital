<?php

declare(strict_types=1);

namespace SOFe\Capital\Database;

use AssertionError;
use Generator;
use poggit\libasynql\DataConnector;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\SqlDialect;
use poggit\libasynql\SqlThread;
use SOFe\AwaitGenerator\Await;

final class QueryBuilder {
    /** @var list<string> */
    private array $queries = [];
    /** @var list<SqlThread::MODE_*> */
    private array $modes = [];
    /** @var array<string, GenericVariable> */
    private array $vars = [];
    /** @var array<string, mixed> */
    private array $args = [];

    public static function new() : self {
        return new self;
    }

    /**
     * @param SqlThread::MODE_* $mode
     * @return $this
     */
    public function addQuery(string $query, int $mode) : self {
        $this->queries[] = $query;
        $this->modes[] = $mode;

        return $this;
    }

    /**
     * @param GenericVariable::TYPE_* $type
     * @return $this
     */
    public function addParam(string $name, string $type, mixed $value) : self {
        $this->vars[$name] = new GenericVariable($name, $type, null);
        $this->args[$name] = $value;

        return $this;
    }

    public function execute(DataConnector $conn, string $dialect) : Generator {
        $stmt = GenericStatementImpl::forDialect($dialect, "raw", $this->queries, "", $this->vars, null, 0);

        $rawQuery = $stmt->format($this->args, match ($dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
            default => throw new AssertionError("unreachable"),
        }, $rawArgs);

        return yield from Await::promise(fn($resolve, $reject) => $conn->executeImplRaw($rawQuery, $rawArgs, $this->modes, $resolve, $reject));
    }
}
