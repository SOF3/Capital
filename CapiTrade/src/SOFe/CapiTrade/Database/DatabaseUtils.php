<?php

declare(strict_types=1);

namespace SOFe\CapiTrade\Database;

use Generator;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use SOFe\Capital\Database\Database;
use SOFe\Capital\Database\LabelManager;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\CapiTrade\CapiTradeException;
use SOFe\CapiTrade\Plugin\MainClass;
use function count;
use function json_decode;

final class DatabaseUtils implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    private RawQueries $raw;

    public function __construct(private Database $db, MainClass $plugin) {
        $conn = $db->getDataConnector();
        foreach (["sqlite/shop.sql", "mysql/shop.sql"] as $sql) {
            $fh = $plugin->getResource($sql);
            if ($fh === null) {
                throw new RuntimeException("Resource $sql is missing");
            }
            $conn->loadQueryFile($fh);
        }
        $this->raw = new RawQueries($conn);
    }

    public function init() : Generator {
        yield from $this->raw->init();
    }

    /**
     * @return Generator<mixed, mixed, mixed, int>
     */
    public function getPrice(UuidInterface $shop) : Generator {
        $rows = yield from $this->raw->getPrice($shop->toString());

        if (count($rows) === 0) {
            throw new CapiTradeException(CapiTradeException::NO_SUCH_SHOP);
        }

        return $rows[0]["price"];
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<string, string>>
     */
    public function getShopAccountSelector(UuidInterface $shop) : Generator {
        $rows = yield from $this->raw->getShopAccountSelector($shop->toString());

        $output = [];
        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row["name"];
            /** @var string $value */
            $value = $row["value"];
            $output[$name] = $value;
        }

        return $output;
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<string, mixed>>
     */
    public function getShopSchemaConfig(UuidInterface $shop) : Generator {
        $rows = yield from $this->raw->getShopSchemaConfig($shop->toString());
        $json = $rows[0]["config"];
        $config = json_decode($json, true);

        return $config;
    }

    /**
     * @return LabelManager<CapiTradeException::*, CapiTradeException>
     */
    public function shopLabels() : LabelManager {
        return new LabelManager(
            database: $this->db,
            labelTable: "capitrade_shop_label",
            labelAlreadyExistsErrorCode: CapiTradeException::SHOP_LABEL_ALREADY_EXISTS,
            labelDoesNotExistErrorCode: CapiTradeException::SHOP_LABEL_DOES_NOT_EXIST,
            throw: fn($code) => new CapiTradeException($code),
        );
    }

    public static function fromSingletonArgs(Database $db, MainClass $plugin) : Generator {
        $self = new self($db, $plugin);

        yield from $self->init();

        return $self;
    }
}
