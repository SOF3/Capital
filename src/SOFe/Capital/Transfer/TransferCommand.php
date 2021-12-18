<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use pocketmine\command\Command;
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
use SOFe\AwaitGenerator\Await;
use SOFe\Capital\Capital;
use SOFe\Capital\MainClass;
use SOFe\Capital\OracleNames;
use SOFe\Capital\TransactionLabels;
use SOFe\InfoAPI\PlayerInfo;
use function assert;
use function count;
use function is_numeric;
use function round;

final class TransferCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(MainClass $plugin, private CommandTransferMethod $method) {
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

        if(!($sender instanceof Player)) {
            $sender->sendMessage("This command can only be used in-game.");
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

        $amount = (int) $amountString;
        if($amount < $this->method->minimumAmount) {
            $sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($amountString, (string) $this->method->minimumAmount)->prefix(TextFormat::RED));
            return;
        }
        if($amount > $this->method->maximumAmount) {
            $sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($amountString, (string) $this->method->minimumAmount)->prefix(TextFormat::RED));
            return;
        }

        if(!is_numeric($amountString)) {
            throw new InvalidCommandSyntaxException;
        }

        Await::f2c(function() use($sender, $recipient, $amount) : Generator {
            $info = new SimpleTransferContextInfo("capital.transfer", [
                "sender" => new PlayerInfo($sender),
                "recipient" => new PlayerInfo($recipient),
            ]);

            $srcLabels = $this->method->src->transform($info);
            $destLabels = $this->method->dest->transform($info);
            $transactionLabels = $this->method->transactionLabels->transform($info);

            if($this->method->rate > 1.0) {
                $transferAmount = $amount;
                $sourceAmount = (int) round($amount * ($this->method->rate - 1.0));
                $sinkAmount = 0;
            } else {
                $transferAmount = (int) round($amount * $this->method->rate);
                $sourceAmount = 0;
                $sinkAmount = $amount - $transferAmount;
            }

            $srcAccounts = yield from Capital::findAccounts($srcLabels);
            $destAccounts = yield from Capital::findAccounts($destLabels);

            if(count($srcAccounts) === 0) {
                $sender->sendMessage(TextFormat::RED . "There are no accounts to send from");
                return;
            }

            if(count($destAccounts) === 0) {
                $sender->sendMessage(TextFormat::RED . "There are no accounts to send to");
                return;
            }

            $transactionId = yield from Capital::awaitTransaction($srcAccounts[0], $destAccounts[0], $transferAmount, $transactionLabels);

            if($sourceAmount > 0 || $sinkAmount > 0) {
                $oracle = yield from Capital::getOracle(OracleNames::TRANSFER);
                if($sourceAmount > 0) {
                    yield from Capital::awaitTransaction($oracle, $destAccounts[0], $sourceAmount, [
                        TransactionLabels::TRANSFER_ORACLE => $transactionId->getId()->toString(),
                    ]);
                }
                if($sinkAmount > 0) {
                    yield from Capital::awaitTransaction($srcAccounts[0], $oracle, $sinkAmount, [
                        TransactionLabels::TRANSFER_ORACLE => $transactionId->getId()->toString(),
                    ]);
                }
            }
        });
    }
}
