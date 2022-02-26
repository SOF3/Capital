<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use AssertionError;
use Generator;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\result\SqlChangeResult;
use poggit\libasynql\result\SqlSelectResult;
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

    public function __construct(private Database $db) {
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<string, TopResultEntry>>
     */
    public function fetchTopAnalytics(TopQueryArgs $query, int $limit, int $page) : Generator {
        $vars = [
            "queryHash" => new GenericVariable("queryHash", GenericVariable::TYPE_STRING, null),
            "offset" => new GenericVariable("offset", GenericVariable::TYPE_INT, null),
            "limit" => new GenericVariable("limit", GenericVariable::TYPE_INT, null),
            "groupingLabel" => new GenericVariable("groupingLabel", GenericVariable::TYPE_STRING, null),
        ];
        $args = [
            "queryHash" => $query->hash(),
            "offset" => ($page - 1) * $limit,
            "limit" => $limit,
            "groupingLabel" => $query->groupingLabel,
        ];

        $ascDesc = $query->ordering === TopQueryArgs::ORDERING_ASC ? "ASC" : "DESC";
        $labelTable = $query->metric->getLabelTable();

        $sql = "SELECT group_value, metric";

        for ($i = 0; $i < count($query->displayLabels); $i++) {
            $sql .= ", t{$i}.value AS display_{$i}";
        }

        $sql .= " FROM capital_analytics_top_cache";
        $sql .= " INNER JOIN $labelTable AS grouping_label ON capital_analytics_top_cache.group_value = grouping_label.value";

        $i = 0;
        foreach ($query->displayLabels as $displayLabel) {
            $sql .= " LEFT JOIN $labelTable AS t{$i} ON grouping_label.id = t{$i}.id";
            $i++;
        }

        $sql .= " WHERE query = :queryHash AND grouping_label.name = :groupingLabel";

        $i = 0;
        foreach ($query->displayLabels as $displayLabel) {
            $sql .= " AND t{$i}.name = :display_{$i}";
            $vars["display_{$i}"] = new GenericVariable("display_{$i}", GenericVariable::TYPE_STRING, $displayLabel);
            $args["display_{$i}"] = $displayLabel;
            $i++;
        }

        $sql .= " ORDER BY metric $ascDesc LIMIT :offset, :limit";

        $stmt = GenericStatementImpl::forDialect($this->db->dialect, "dynamic-analytics-fetch", [$sql], "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match ($this->db->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->db->getDataConnector()->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_SELECT], yield Await::RESOLVE, yield Await::REJECT);
        [$result] = yield Await::ONCE;
        if (!($result instanceof SqlSelectResult)) {
            throw new AssertionError("libasynql returned incorrect result type");
        }
        $rows = $result->getRows();

        $output = [];
        $rank = 0;
        foreach ($rows as $row) {
            $key = $row["group_value"];
            if (!is_string($key)) {
                throw new AssertionError("libasynql returned value of incorrect data type");
            }

            $metric = $row["metric"];
            if (!is_float($metric)) {
                throw new AssertionError("libasynql returned value of incorrect data type");
            }

            $i = 0;
            $displays = [];
            foreach ($query->displayLabels as $infoName => $displayLabel) {
                $value = $row["display_{$i}"];
                if (!is_string($value)) {
                    throw new AssertionError("libasynql returned value of incorrect data type");
                }

                $displays[$infoName] = $value;
                $i++;
            }

            $output[$key] = new TopResultEntry($rank, $metric, $displays);
            $rank++;
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
            "queryHash" => new GenericVariable("queryHash", GenericVariable::TYPE_STRING, null),
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

        $sql = "INSERT INTO capital_analytics_top_cache (query, metric, last_updated, last_updated_with, group_value) ";
        $sql .= "SELECT :queryHash, NULL, CURRENT_TIMESTAMP, :runId, grouping_label.value ";
        $sql .= "FROM $labelTable AS grouping_label ";

        for ($i = 0; $i < count($query->labelSelector->getEntries()); $i++) {
            $sql .= "INNER JOIN $labelTable AS selector_{$i} USING (id) ";
        }

        $sql .= "WHERE grouping_label.name = :groupingLabel";
        $sql .= " AND grouping_label.value NOT IN (SELECT group_value FROM capital_analytics_top_cache WHERE query = :queryHash)";

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

        $sql .= " LIMIT :limit";

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

        $metricExpr .= " WHERE grouping_label.name = :groupingLabel AND grouping_label.value = capital_analytics_top_cache.group_value";

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

        $sql = "UPDATE capital_analytics_top_cache SET metric = ($metricExpr) WHERE last_updated_with = :runId";
        $stmt = GenericStatementImpl::forDialect($this->db->dialect, "dynamic-analytics-compute", [$sql], "", $vars, __FILE__, __LINE__);

        $rawQuery = $stmt->format($args, match ($this->db->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->db->getDataConnector()->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_CHANGE], yield Await::RESOLVE, yield Await::REJECT);
        yield Await::ONCE;
    }
}
