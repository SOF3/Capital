<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use AssertionError;
use Generator;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\result\SqlChangeResult;
use poggit\libasynql\SqlDialect;
use poggit\libasynql\SqlThread;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\LabelSelector;

use function count;
use function is_float;
use function is_string;

final class DatabaseUtils implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    private function __construct(private Database $db) {
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<string, float>>
     */
    public function fetchTopAnalytics(TopQueryArgs $query, int $limit, int $page) : Generator {
        $hash = $query->hash();
        $offset = ($page - 1) * $limit;
        $orderSign = $query->ordering === TopQueryArgs::ORDERING_DESC ? -1 : 1;

        $rows = yield from $this->db->raw->analyticsFetchTop($hash, $limit, $offset, $orderSign);

        $output = [];
        foreach ($rows as $row) {
            $key = $row["group_value"];
            if (!is_string($key)) {
                throw new AssertionError("libasynql returned value of incorrect data type");
            }

            $metric = $row["metric"];
            if (!is_float($metric)) {
                throw new AssertionError("libasynql returned value of incorrect data type");
            }

            $output[$key] = $metric;
        }
        return $output;
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     */
    public function fetchTopAnalyticsCount(TopQueryArgs $query) : Generator {
        $rows = yield from $this->db->raw->analyticsCount($query->hash());
        return $rows[0]["cnt"];
    }

    /**
     * @return VoidPromise
     */
    public function collect(string $runId, TopQueryArgs $query, int $expiry, int $batchSize) : Generator {
        $newCount = yield from $this->collectNew($runId, $query, $batchSize);
        $remaining = $batchSize - $newCount;
        if ($remaining > 0) {
            yield from $this->collectOld($runId, $query, $expiry, $remaining);
        }
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     */
    private function collectNew(string $runId, TopQueryArgs $query, int $batchSize) : Generator {
        $vars = [
            "queryHash" => new GenericVariable("hash", GenericVariable::TYPE_STRING, null),
            "runId" => new GenericVariable("runId", GenericVariable::TYPE_STRING, null),
            "groupingLabel" => new GenericVariable("groupingLabel", GenericVariable::TYPE_STRING, null),
            "limit" => new GenericVariable("limit", GenericVariable::TYPE_INT, null),
        ];
        $args = [
            "queryHash" => $query->hash(),
            "runId" => $runId,
            "groupingLabel" => $query->groupingLabel,
            "limit" => $batchSize,
        ];

        $labelTable = $query->metric->getLabelTable();

        $sql = "INSERT INTO analytics_top_cache (query, metric, last_updated, last_updated_with, group_value) ";
        $sql .= "SELECT :queryHash, NULL, CURRENT_TIMESTAMP, :runId, grouping_label.value ";
        $sql .= "FROM $labelTable AS grouping_label ";

        for ($i = 0; $i < count($query->labelSelector->getEntries()); $i++) {
            $sql .= "INNER JOIN $labelTable AS selector_{$i} USING (id) ";
        }

        $sql .= "WHERE grouping_label.name = :groupingLabel";

        $i = 0;
        foreach ($query->labelSelector->getEntries() as $name => $value) {
            $sql .= " AND selector_{$i}.name = :selector_name{$i}";
            $vars["selector_name{$i}"] = new GenericVariable("selector_name{$i}", GenericVariable::TYPE_STRING, null);
            $args["selector_name{$i}"] = $name;

            if ($value !== LabelSelector::ANY_VALUE) {
                $sql .= " AND selector_{$i}.value = :selector_value{$i}";
                $vars["selector_value{$i}"] = new GenericVariable("selector_value{$i}", GenericVariable::TYPE_STRING, null);
                $args["selector_value{$i}"] = $value;
            }

            $i++;
        }

        $stmt = GenericStatementImpl::forDialect($this->db->dialect, "dynamic-analytics-collect-new", [$sql], "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match ($this->db->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->db->getDataConnector()->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_CHANGE], yield Await::RESOLVE, yield Await::REJECT);
        [$result] = yield Await::ONCE;
        if (!($result instanceof SqlChangeResult)) {
            throw new AssertionError("libasynql returned incorrect result type");
        }
        return $result->getAffectedRows();
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     */
    private function collectOld(string $runId, TopQueryArgs $query, int $expiry, int $remainingBatchSize) : Generator {
        return yield from $this->db->raw->analyticsCollectUpdates($query->hash(), $runId, $expiry, $remainingBatchSize);
    }

    /**
     * @return VoidPromise
     */
    public function compute(string $runId, TopQueryArgs $query) : Generator {
        $vars = [
            "runId" => new GenericVariable("runId", GenericVariable::TYPE_STRING, null),
            "groupingLabel" => new GenericVariable("groupingLabel", GenericVariable::TYPE_STRING, null),
        ];
        $args = [
            "runId" => $runId,
            "groupingLabel" => $query->groupingLabel,
        ];

        $mainTable = $query->metric->getMainTable();
        $labelTable = $query->metric->getLabelTable();

        $expr = $query->metric->getExpr();
        $metricExpr = "SELECT $expr FROM $labelTable AS grouping_label";

        for ($i = 0; $i < count($query->labelSelector->getEntries()); $i++) {
            $metricExpr .= " INNER JOIN $labelTable AS t{$i} USING (id)";
        }

        if (!$query->metric->usesIdOnly()) {
            $metricExpr .= " INNER JOIN $mainTable USING (id)";
        }

        $metricExpr .= " WHERE grouping_label.name = :groupingLabel AND grouping_label.value = analytics_top_cache.group_value";

        $i = 0;
        foreach ($query->labelSelector->getEntries() as $name => $value) {
            $metricExpr .= " AND t{$i}.name = :name{$i}";
            $vars["name{$i}"] = new GenericVariable("name{$i}", GenericVariable::TYPE_STRING, null);
            $args["name{$i}"] = $name;

            if ($value !== LabelSelector::ANY_VALUE) {
                $metricExpr .= " AND t{$i}.value = :value{$i}";
                $vars["value{$i}"] = new GenericVariable("value{$i}", GenericVariable::TYPE_STRING, null);
                $args["value{$i}"] = $value;
            }

            $i++;
        }

        $sql = "UPDATE analytics_top_cache SET metric = ($metricExpr) WHERE last_updated_with = :runId";
        $stmt = GenericStatementImpl::forDialect($this->db->dialect, "dynamic-analytics-compute", [$sql], "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match ($this->db->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->db->getDataConnector()->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_CHANGE], yield Await::RESOLVE, yield Await::REJECT);
        yield Await::ONCE;
    }
}
