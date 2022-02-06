<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use pocketmine\Server;
use SOFe\Capital\Capital;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\Capital\Plugin\MainClass;
use SOFe\Capital\Schema\Schema;
use function strpos;
use function substr;

/**
 * Transfer money by running a command.
 */
final class CommandMethod implements Method {
    /**
     * @param string $command The name of the command.
     * @param string $permission The permission for the command.
     * @param bool $defaultOpOnly Whether the permission is given to ops only by default.
     * @param AccountTarget $src Selects the accounts to take money from.
     * @param AccountTarget $dest Selects the accounts to send money to.
     * @param float $rate The transfer rate. Must be positive. If $rate > 1.0, an extra transaction from `capital/oracle=transfer` to `$dest` is performed. If `0.0 < $rate < 1.0`, only `$rate` of the amount is transferred, and an extra transaction from `$src` to `capital/oracle=transfer` is performed.
     * @param int $minimumAmount The minimum amount to transfer. This should be a non-negative integer.
     * @param int $maximumAmount The maximum amount to transfer. Note that this does not override the original account valueMin/valueMax labels.
     * @param ParameterizedLabelSet<ContextInfo> $transactionLabels The labels set on the transaction.
     * @param Messages $messages The messages to use.
     */
    public function __construct(
        public string $command,
        public string $permission,
        public bool $defaultOpOnly,
        public AccountTarget $src,
        public AccountTarget $dest,
        public float $rate,
        public int $minimumAmount,
        public int $maximumAmount,
        public ParameterizedLabelSet $transactionLabels,
        public Messages $messages,
    ) {}

    public function register(MainClass $plugin, Capital $api) : void {
        $command = new Command($plugin, $api, $this);
        Server::getInstance()->getCommandMap()->register("capital", $command);
    }

    public static function parse(Parser $parser, Schema $schema, ?DefaultCommand $default = null) : self {
        $command = $parser->expectString("command", $default?->command ?? "transfer-command", <<<'EOT'
            This is the name of the command that will be run.
            EOT);
        if ($command === "") {
            $command = $parser->setValue("command", "transfer-command", "The command's name (key \"command\") must not be empty.");
        } elseif (($i = strpos($command, " ")) !== false) {
            $command = substr($command, 0, $i);
            $command = $parser->setValue("command", $command === "" ? "transfer-command" : $command, "The command's name (key \"command\") must not have spaces.");
        }

        $permission = $parser->expectString("permission", $default?->permission ?? "capital.transfer.unspecified", <<<'EOT'
            Users must have this permission to run this command.
            It will be created for you.
            EOT);
        if ($permission === "") {
            $permission = $parser->setValue("permission", "capital.transfer.unspecified", "The command's permission (key \"permission\") must not be empty.");
        } elseif (($i = strpos($permission, " ")) !== false) {
            $permission = substr($permission, 0, $i);
            $permission = $parser->setValue("permission", $permission === "" ? "capital.transfer.unspecified" : $permission, "The command's permission (key \"permission\") must not have spaces.");
        }

        $defaultOpOnly = $parser->expectBool("default-op", $default?->defaultOpOnly ?? true, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = AccountTarget::parse($parser->enter("src", <<<'EOT'
            The "source" to take money from.
            EOT), $schema, $default?->src ?? AccountTarget::TARGET_SENDER);

        $dest = AccountTarget::parse($parser->enter("dest", <<<'EOT'
            The "destination" to give money to.
            EOT), $schema, $default?->dest ?? AccountTarget::TARGET_RECIPIENT);

        $rate = $parser->expectNumber("rate", $default?->rate ?? 1.0, <<<'EOT'
            The exchange rate, or how much of the original money is sent.
            When using "currency" schema, this allows transferring between
            accounts of different currencies.
            EOT);

        $minimumAmount = $parser->expectInt("minimum-amount", $default?->minimumAmount ?? 0, <<<'EOT'
            The minimum amount of money that can be transferred each time.
            EOT);

        $maximumAmount = $parser->expectInt("maximum-amount", $default?->maximumAmount ?? 0, <<<'EOT'
            The maximum amount of money that can be transferred each time.
            EOT);

        /** @var ParameterizedLabelSet<ContextInfo> $transactionLabels */
        $transactionLabels = ParameterizedLabelSet::parse($parser->enter("transaction-labels", <<<'EOT'
            These are labels to add to the transaction.
            You can match by these labels to identify how players earn and lose money.
            Labels are formatted using InfoAPI syntax.
            EOT), $default?->transactionLabels ?? []);

        $messages = Messages::parse($parser->enter("messages", null), $default?->messages);

        return new CommandMethod($command, $permission, $defaultOpOnly, $src, $dest, $rate, $minimumAmount, $maximumAmount, $transactionLabels, $messages);
    }
}
