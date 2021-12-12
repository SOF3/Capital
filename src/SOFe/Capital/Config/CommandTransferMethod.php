<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

/**
 * Transfer money by running a command.
 */
final class CommandTransferMethod implements TransferMethod {
    /**
     * @param string $command The name of the command.
     * @param string $permission The permission for the command.
     * @param ParameterizedLabelSelector<SimpleTransferContextInfo> $src Selects the accounts to take money from.
     * @param ParameterizedLabelSelector<SimpleTransferContextInfo> $dest Selects the accounts to send money to.
     * @param float $rate The transfer rate. Must be positive. If $rate > 1.0, an extra transaction from `capital/oracle=transfer` to `$dest` is performed. If `0.0 < $rate < 1.0`, only `$rate` of the amount is transferred, and an extra transaction from `$src` to `capital/oracle=transfer` is performed.
     * @param int $minimumAmount The minimum amount to transfer. This should be a non-negative integer.
     * @param int $maximumAmount The maximum amount to transfer. Note that this does not override the original account valueMin/valueMax labels.
     * @param ParameterizedLabelSet<SimpleTransferContextInfo> $transactionLabels The labels set on the transaction.
     */
    public function __construct(
        public string $command,
        public string $permission,
        public ParameterizedLabelSelector $src,
        public ParameterizedLabelSelector $dest,
        public float $rate,
        public int $minimumAmount,
        public int $maximumAmount,
        public ParameterizedLabelSet $transactionLabels,
    ) {}
}
