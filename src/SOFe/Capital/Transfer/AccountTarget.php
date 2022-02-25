<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use SOFe\Capital\AccountRef;
use SOFe\Capital\Capital;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\OracleNames;
use SOFe\Capital\Schema;

final class AccountTarget {
    public const TARGET_SYSTEM = "system";
    public const TARGET_SENDER = "sender";
    public const TARGET_RECIPIENT = "recipient";

    /**
     * @param self::TARGET_* $target
     */
    public function __construct(
        public string $target,
        public ?Schema\Schema $schema,
    ) {
    }

    /**
     * @param self::TARGET_* $defaultTarget
     */
    public static function parse(Parser $parser, Schema\Schema $rootSchema, string $defaultTarget = self::TARGET_SYSTEM) : self {
        $target = $parser->expectString("of", $defaultTarget, <<<'EOT'
            Can be "system", "sender", or "recipient".
            If "sender" is used, this command will only be usable by players.
            (i.e. cannot be used from console).
            EOT);
        $target = match ($target) {
            "system" => self::TARGET_SYSTEM,
            "sender" => self::TARGET_SENDER,
            "recipient" => self::TARGET_RECIPIENT,
            default => $parser->setValue("of", $defaultTarget, "Expected key \"of\" to be \"system\", \"sender\", or \"recipient\"."),
        };

        $schema = null;
        if ($target !== self::TARGET_SYSTEM) {
            $schema = $rootSchema->cloneWithConfig($parser);
        }

        /**
         * PHPStan seems to lose type information when $parser->failSafe
         * is called even though it says that it returns the type passed into it.
         * This means that $target is "string" and not "self::TARGET_*" anymore.
         *
         * @var self::TARGET_* $target
         */
        return new self($target, $schema);
    }

    /**
     * @param list<string> $args
     * @return Generator<mixed, mixed, mixed, AccountRef|null>
     * @throws InvalidArgumentException if the arguments cannot be inferred.
     */
    public function findAccounts(Capital $api, array &$args, CommandSender $sender, Player $recipient) : Generator {
        return match ($this->target) {
            self::TARGET_SYSTEM => yield from $api->getOracle(OracleNames::TRANSFER),
            self::TARGET_SENDER => $sender instanceof Player ? yield from $this->getSelectorForPlayer($api, $args, $sender, $sender) : null,
            self::TARGET_RECIPIENT => yield from $this->getSelectorForPlayer($api, $args, $sender, $recipient),
        };
    }

    /**
     * @param list<string> $args
     * @return Generator<mixed, mixed, mixed, AccountRef>
     * @throws InvalidArgumentException if the arguments cannot be inferred.
     */
    private function getSelectorForPlayer(Capital $api, array &$args, CommandSender $sender, Player $player) : Generator {
        if ($this->schema === null) {
            throw new AssumptionFailedError("Cannot call getSelectorForPlayer if target is TARGET_SYSTEM");
        }

        $accounts = yield from $api->findAccountsIncomplete($player, $this->schema, $args, $sender);
        return $accounts[0];
    }
}
