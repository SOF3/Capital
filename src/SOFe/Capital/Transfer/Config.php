<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Schema\Config as SchemaConfig;


final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /** The label applied on normal payment transactions. */
    public const LABEL_PAYMENT = "payment";

    /** The label applied on operator-related transactions. */
    public const LABEL_OPERATOR = "operator";

    /**
     * @param list<PayCommand> $payCommands
     * @param list<AlterCommand> $alterCommands
     */
    public function __construct(
        public array $payCommands,
        public array $alterCommands,
    ) {
    }

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        $transferParser = $config->enter("transfer", <<<'EOT'
            "transfer" tells Capital what methods admins and players can send money through.
            EOT);

        /** @var SchemaConfig $schemaConfig */
        $schemaConfig = yield from $raw->awaitConfigInternal(SchemaConfig::class);
        $schema = $schemaConfig->schema;

        $payCommandsParser = $transferParser->enter("payment-commands", <<<'EOT'
            These are payment commands from one player to another player.
            EOT, $isNew);
        if ($isNew) {
            PayCommand::parse($payCommandsParser->enter("pay", "An example command that pays money to another player"), $schema, "pay");
        }

        $addMoneyCommandsParser = $transferParser->enter("add-money-commands", <<<'EOT'
            These are commands that allow admins to add money to a player.
            EOT, $isNew);
        if ($isNew) {
            AlterCommand::parse($addMoneyCommandsParser->enter("addmoney", "Adds money to a player"), $schema, "addmoney", true);
        }

        $takeMoneyCommandsParser = $transferParser->enter("take-money-commands", <<<'EOT'
            These are commands that allow admins to take money from a player.
            EOT, $isNew);
        if ($isNew) {
            AlterCommand::parse($takeMoneyCommandsParser->enter("takemoney", "Takes money to a player"), $schema, "takemoney", false);
        }

        $payCommands = [];
        foreach ($payCommandsParser->getKeys() as $commandName) {
            $payCommands[] = PayCommand::parse($payCommandsParser->enter($commandName, null), $schema, $commandName);
        }
        $alterCommands = [];
        foreach ($addMoneyCommandsParser->getKeys() as $commandName) {
            $alterCommands[] = AlterCommand::parse($addMoneyCommandsParser->enter($commandName, null), $schema, $commandName, true);
        }
        foreach ($takeMoneyCommandsParser->getKeys() as $commandName) {
            $alterCommands[] = AlterCommand::parse($takeMoneyCommandsParser->enter($commandName, null), $schema, $commandName, false);
        }

        return new self($payCommands, $alterCommands);
    }
}
