<?php

declare(strict_types=1);

namespace SOFe\Capital\Analytics;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\Capital\Config\DynamicCommand;
use SOFe\InfoAPI\InfoAPI;
use SOFe\InfoAPI\PlayerInfo;

final class PlayerInfoCommand {
    public function __construct(
        public DynamicCommand $command,
        public string $template,
    ) {
    }

    public function register(Plugin $plugin) : void {
        $this->command->register($plugin, function(CommandSender $sender, array $args) : void {
            if (isset($args[0])) {
                if (!$this->command->checkSubPermission($sender, "other")) {
                    $sender->sendMessage(KnownTranslationFactory::commands_generic_permission()->prefix(TextFormat::RED));
                    return;
                }

                $player = Server::getInstance()->getPlayerByPrefix($args[0]);

                if ($player === null) {
                    $sender->sendMessage(KnownTranslationFactory::commands_generic_player_notFound()->prefix(TextFormat::RED));
                    return;
                }
            } else {
                if (!($sender instanceof Player)) {
                    throw new InvalidCommandSyntaxException;
                }

                if (!$this->command->checkSubPermission($sender, "self")) {
                    $sender->sendMessage(KnownTranslationFactory::commands_generic_permission()->prefix(TextFormat::RED));
                    return;
                }

                $player = $sender;
            }

            $sender->sendMessage(InfoAPI::resolve($this->template, new PlayerInfo($player)));
        });
    }
}
