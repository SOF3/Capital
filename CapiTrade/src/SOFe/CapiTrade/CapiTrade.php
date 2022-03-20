<?php

declare(strict_types=1);

namespace SOFe\CapiTrade;

use Generator;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\CapitalException;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\LabelSet;
use SOFe\Capital\ParameterizedLabelSelector;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\InfoAPI\PlayerInfo;
use function count;
use function json_decode;

final class CapiTrade implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const VERSION = "0.1.0";

    public function __construct(private Database\DatabaseUtils $db, private Capital $capital) {
    }

    public function executeShop(ShopRef $shopId, Player $customer) : Generator {
        /**
         * @var array<string, string> $labels
         * @var int $price
         * @var array<string, string> $shopAccSelectorRaw
         * @var array<string, mixed> $schemaConfig
         */
        [$labels, $price, $shopAccSelectorRaw, $schemaConfig] = yield from Await::all([
            $this->db->shopLabels()->getAll($shopId->getId()),
            $this->db->getPrice($shopId->getId()),
            $this->db->getShopAccountSelector($shopId->getId()),
            $this->db->getShopSchemaConfig($shopId->getId()),
        ]);

        $shopAccSelector = new ParameterizedLabelSelector($shopAccSelectorRaw);
        $shopAccSelector = $shopAccSelector->transform(new PlayerInfo($customer));

        $shopAccs = yield from $this->capital->findAccounts($shopAccSelector);
        if (count($shopAccs) === 0) {
            throw new CapiTradeException(CapiTradeException::SHOP_HAS_NO_ACCOUNT);
        }
        $shopAcc = $shopAccs[0];

        $schema = $this->capital->completeConfig($schemaConfig);
        $customerAccs = yield from $this->capital->findAccountsComplete($customer, $schema);
        $customerAcc = $customerAccs[0];

        if ($price > 0) {
            $src = $customerAcc;
            $dest = $shopAcc;
        } else {
            $src = $shopAcc;
            $dest = $customerAcc;
            $price *= -1;
        }

        if (isset($labels[ShopLabels::TRANSACTION_LABELS])) {
            $transactionLabels = json_decode($labels[ShopLabels::TRANSACTION_LABELS], true);
            $transactionLabelSet = new ParameterizedLabelSet($transactionLabels);
            $transactionLabelSet = $transactionLabelSet->transform(new PlayerInfo($customer));
        } else {
            $transactionLabelSet = new LabelSet([]);
        }

        $transactionException = null;
        $event = new ShopExecuteEvent($shopId, $labels, $customer, function() use ($src, $dest, $price, $transactionLabelSet, $customer, &$transactionException) : Generator {
            try {
                yield from $this->capital->transact($src, $dest, $price, $transactionLabelSet, [$customer], true);
                return true;
            } catch (CapitalException $ex) {
                $transactionException = $ex;
                return false;
            }
        });
        $event->call();

        yield from $event->waitDone();

        $rejection = $event->getRejection();
        if ($rejection !== null) {
            throw new CapiTradeException(CapiTradeException::EVENT_CANCELLED, $rejection);
        }

        if ($transactionException !== null) {
            throw new CapiTradeException(CapiTradeException::TRANSACTION_FAILED, $transactionException);
        }
    }

    public function deleteShop(int $shopId, int $accessId) : Generator {
        yield from $this->db->deleteAccess($accessId);
        yield from $this->db->deleteIfNoAccess($shopId);
    }
}
