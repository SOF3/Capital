<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\OracleNames;
use SOFe\Capital\Schema\Schema;

class AccountTarget {
    const TARGET_SYSTEM = "system";
    const TARGET_SENDER = "sender";
    const TARGET_RECIPIENT = "recipient";

    /**
     * @param self::TARGET_* $target
     */
    public function __construct(
        private string $target,
        private Schema $schema,
    ) {
    }

    /**
     * @param self::TARGET_* $defaultTarget
     */
    public static function parse(Parser $parser, Schema $schema, string $defaultTarget = self::TARGET_SYSTEM) : self {
        $schema = $schema->cloneWithConfig($parser);
        $target = $parser->expectString("of", $defaultTarget, <<<'EOT'
            Can be "system", "sender", or "recipient".
            If "sender" is used, this command will only be usable by players.
            (i.e. cannot be used from console).
            EOT);
        $target = match ($target) {
            "system" => self::TARGET_SYSTEM,
            "sender" => self::TARGET_SENDER,
            "recipient" => self::TARGET_RECIPIENT,
            default => $parser->failSafe($defaultTarget, "Expected key \"of\" to be \"system\", \"sender\", or \"recipient\".")
        };
        /**
         * PHPStan seems to loose type information when $parser->failSafe
         * is called even though it says that it returns the type passed into it.
         * This means that $target is "string" and not "self::TARGET_*" anymore.
         *
         * @var self::TARGET_* $target
         */
        return new self($target, $schema);
    }

    public function getSelector(CommandSender $sender, Player $recipient) : ?LabelSelector {
        return match ($this->target) {
            self::TARGET_SYSTEM => new LabelSelector([ AccountLabels::ORACLE => OracleNames::TRANSFER ]),
            self::TARGET_SENDER => ($sender instanceof Player) ? $this->schema->getSelector($sender) : null,
            self::TARGET_RECIPIENT => $this->schema->getSelector($recipient)
        };
    }
}
