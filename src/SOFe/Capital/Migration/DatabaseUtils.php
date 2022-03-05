<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use Generator;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\SqlDialect;
use poggit\libasynql\SqlThread;
use Ramsey\Uuid\Uuid;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use function count;


final class DatabaseUtils implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public function __construct(private Database $db) {
    }

    /**
     * @param list<Entry> $entries
     * @return VoidPromise
     */
    public function addEntries(array $entries) : Generator {
        $sql = "INSERT INTO capital_acc (id, value, touch) VALUES ";
        $vars = [];
        $args = [];

        $ids = [];

        foreach ($entries as $i => $entry) {
            if ($i > 0) {
                $sql .= ", ";
            }

            $sql .= "(:id{$i}, :value{$i}, CURRENT_TIMESTAMP)";

            $id = Uuid::uuid4()->toString();
            $ids[] = $id;
            $vars["id{$i}"] = new GenericVariable("id{$i}", GenericVariable::TYPE_STRING, null);
            $args["id{$i}"] = $id;

            $vars["value{$i}"] = new GenericVariable("value{$i}", GenericVariable::TYPE_INT, null);
            $args["value{$i}"] = $entry->balance;
        }

        $stmt = GenericStatementImpl::forDialect($this->db->dialect, "dynamic-migration-add-account", [$sql], "", $vars, __FILE__, __LINE__);

        $rawArgs = [];
        $rawQuery = $stmt->format($args, match ($this->db->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->db->getDataConnector()->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_CHANGE], yield Await::RESOLVE, yield Await::REJECT);
        yield Await::ONCE;


        $sql = "INSERT INTO capital_acc_label (id, name, value) VALUES ";
        $vars = [];
        $args = [];
        $first = true;

        $j = 0;

        foreach ($entries as $i => $entry) {
            if (count($entry->labels) === 0) {
                continue;
            }

            $id = $ids[$i];

            $vars["id{$i}"] = new GenericVariable("id{$i}", GenericVariable::TYPE_STRING, null);
            $args["id{$i}"] = $ids[$i];

            foreach ($entry->labels as $key => $value) {
                if ($first) {
                    $first = false;
                } else {
                    $sql .= ", ";
                }

                $j += 1;

                $sql .= "(:id{$i}, :name{$j}, :value{$j})";

                $vars["name{$j}"] = new GenericVariable("name{$j}", GenericVariable::TYPE_STRING, null);
                $args["name{$j}"] = $key;

                $vars["value{$j}"] = new GenericVariable("value{$j}", GenericVariable::TYPE_STRING, null);
                $args["value{$j}"] = $value;
            }
        }

        $stmt = GenericStatementImpl::forDialect($this->db->dialect, "dynamic-migration-add-account-label", [$sql], "", $vars, __FILE__, __LINE__);

        $rawArgs = [];
        $rawQuery = $stmt->format($args, match ($this->db->dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?",
        }, $rawArgs);

        $this->db->getDataConnector()->executeImplRaw($rawQuery, $rawArgs, [SqlThread::MODE_CHANGE], yield Await::RESOLVE, yield Await::REJECT);
        yield Await::ONCE;
    }
}
