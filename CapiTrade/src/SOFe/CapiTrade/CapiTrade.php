<?php

declare(strict_types=1);

namespace SOFe\CapiTrade;

use Generator;
use pocketmine\player\Player;
use SOFe\Capital\Capital;
use SOFe\Capital\CapitalException;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

final class CapiTrade implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    public const VERSION = "0.1.0";

    public function __construct(private Database\DatabaseUtils $db, private Capital $capital) {
    }

    public function executeShop(int $shopId, Player $customer) : Generator {
        $labels = yield from $this->db->getLabels($shopId);
        $event = new ShopUseEvent($shopId, $labels, $customer);
        $event->call();
        yield from $event->getWaitGroup()->wait();

        $ok = false;
        try {
            $rejection = $event->getRejection();
            if($rejection !== null) {
                throw new CapitalException(CapitalException::EVENT_CANCELLED, $rejection);
            }

            yield from $this->capital->transact();

            foreach($event->getProducts() as $product) {
                $product->execute();
            }

            $ok = true;
        } finally {
            if(!$ok) {
                foreach($event->getProducts() as $product) {
                    $product->cancel();
                }
            }
        }
    }

    public function deleteShop(int $shopId, int $accessId) : Generator {
        yield from $this->db->deleteAccess($accessId);
        yield from $this->db->deleteIfNoAccess($shopId);
    }
}
