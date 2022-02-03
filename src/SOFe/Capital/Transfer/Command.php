<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use pocketmine\command\Command as PmCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\CapitalException;
use SOFe\Capital\OracleNames;
use SOFe\Capital\Plugin\MainClass;
use SOFe\Capital\TransactionLabels;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\NumberInfo;
use SOFe\InfoAPI\PlayerInfo;
use function array_sum;
use function assert;
use function count;
use function is_numeric;
use function round;

final class Command extends PmCommand implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(MainClass $plugin, private Capital $api, private CommandMethod $method) {
        parent::__construct($method->command, "TODO", "TODO");

        $permManager = PermissionManager::getInstance();
        $permManager->addPermission(new Permission($method->permission));
        $root = $permManager->getPermission($method->defaultOpOnly ? DefaultPermissions::ROOT_OPERATOR : DefaultPermissions::ROOT_USER);
        assert($root !== null, "Default permission root not registered");
        $root->addChild($method->permission, true);

        $this->setPermission($method->permission);
        $this->owningPlugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$this->testPermission($sender)) {
            return;
        }

        if(!isset($args[1])) {
            throw new InvalidCommandSyntaxException;
        }

        $recipientNamePrefix = $args[0];
        $amountString = $args[1];

        $recipient = Server::getInstance()->getPlayerByPrefix($recipientNamePrefix);
        if($recipient === null) {
            $sender->sendMessage(KnownTranslationFactory::commands_generic_player_notFound()->prefix(TextFormat::RED));
            return;
        }

        if(!is_numeric($amountString)) {
            throw new InvalidCommandSyntaxException;
        }

        $amount = (int) $amountString;
        if($amount < $this->method->minimumAmount) {
            $sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($amountString, (string) $this->method->minimumAmount)->prefix(TextFormat::RED));
            return;
        }
        if($amount > $this->method->maximumAmount) {
            $sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($amountString, (string) $this->method->minimumAmount)->prefix(TextFormat::RED));
            return;
        }

        Await::f2c(function() use($sender, $recipient, $amount) : Generator {
            if($this->method->rate > 1.0) {
                $transferAmount = $amount;
                $sourceAmount = (int) round($amount * ($this->method->rate - 1.0));
                $sinkAmount = 0;
            } else {
                $transferAmount = (int) round($amount * $this->method->rate);
                $sourceAmount = 0;
                $sinkAmount = $amount - $transferAmount;
            }

            $info = new ContextInfo(
                sender: $sender instanceof Player ? new PlayerInfo($sender) : null,
                recipient: new PlayerInfo($recipient),
                sentAmount: new NumberInfo((float) ($transferAmount + $sinkAmount)),
                receivedAmount: new NumberInfo((float) $transferAmount + $sourceAmount),
            );

            $srcLabels = $this->method->src->getSelector($sender, $recipient);
            if ($srcLabels === null) {
                if (!$sender instanceof Player) {
                    $sender->sendMessage(InfoAPI::resolve($this->method->messages->playerOnlyCommand, $info));
                } else {
                    // This should never happen
                    $sender->sendMessage(InfoAPI::resolve($this->method->messages->internalError, $info));
                }
                return;
            }

            $destLabels = $this->method->dest->getSelector($sender, $recipient);
            if ($destLabels === null) {
                if (!$sender instanceof Player) {
                    $sender->sendMessage(InfoAPI::resolve($this->method->messages->playerOnlyCommand, $info));
                } else {
                    // This should never happen
                    $sender->sendMessage(InfoAPI::resolve($this->method->messages->internalError, $info));
                }
                return;
            }

            $transactionLabels = $this->method->transactionLabels->transform($info);

            $srcAccounts = yield from $this->api->findAccounts($srcLabels);
            $destAccounts = yield from $this->api->findAccounts($destLabels);

            if(count($srcAccounts) === 0) {
                $sender->sendMessage(InfoAPI::resolve($this->method->messages->noSourceAccounts, $info));
                return;
            }

            if(count($destAccounts) === 0) {
                $sender->sendMessage(InfoAPI::resolve($this->method->messages->noDestinationAccounts, $info));
                return;
            }


            if($sourceAmount > 0 || $sinkAmount > 0) {
                $transactionId = Uuid::uuid4();

                $oracle = yield from $this->api->getOracle(OracleNames::TRANSFER);
                $labels2 = [
                    TransactionLabels::TRANSFER_ORACLE => $transactionId->toString(),
                ];

                if($sourceAmount > 0) {
                    $src2 = $oracle;
                    $dest2 = $destAccounts[0];
                    $amount2 = $sourceAmount;
                } else {
                    assert($sinkAmount > 0);
                    $src2 = $srcAccounts[0];
                    $dest2 = $oracle;
                    $amount2 = $sinkAmount;
                }

                $promise = $this->api->transact2(
                    $srcAccounts[0], $destAccounts[0], $transferAmount, $transactionLabels,
                    $src2, $dest2, $amount2, $labels2,
                    $transactionId, null, // we don't need to specify the oracle transaction ID
                );
            } else {
                $promise = $this->api->transact($srcAccounts[0], $destAccounts[0], $transferAmount, $transactionLabels);
            }

            try {
                yield from $promise;
            } catch(CapitalException $ex) {
                $error = match($ex->getCode()) {
                    CapitalException::SOURCE_UNDERFLOW => $this->method->messages->underflow,
                    CapitalException::DESTINATION_OVERFLOW => $this->method->messages->underflow,
                    default => $this->method->messages->internalError,
                };
                $sender->sendMessage(InfoAPI::resolve($error, $info));
                return;
            }

            [$srcValues, $destValues] = yield from Await::all([
                $this->api->getBalances($srcAccounts),
                $this->api->getBalances($destAccounts),
            ]);

            $successInfo = new SuccessContextInfo(
                srcBalance: new NumberInfo((float) array_sum($srcValues)),
                destBalance: new NumberInfo((float) array_sum($destValues)),
                fallback: $info,
            );

            $sender->sendMessage(InfoAPI::resolve($this->method->messages->notifySenderSuccess, $successInfo));
            $recipient->sendMessage(InfoAPI::resolve($this->method->messages->notifyRecipientSuccess, $successInfo));
        });
    }
}
