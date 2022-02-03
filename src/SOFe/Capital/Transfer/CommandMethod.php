<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use pocketmine\Server;
use SOFe\Capital\Capital;
use SOFe\Capital\ParameterizedLabelSet;
use SOFe\Capital\Plugin\MainClass;

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
}
