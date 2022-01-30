<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\Config\Parser;

class MethodFactory
{
    public static function build(Parser $parser, string $methodName): Method
    {
        $type = $parser->expectString("type", "command", <<<'EOT'
        The type of the method. Must be "command"
        EOT);

        if ($type !== "command") {
            $type = $parser->failSafe("command", "Expected \"command\" for key \"type\" got \"$type\"");
        }

        // TODO: validate $command
        $command = $parser->expectString("command", $methodName, <<<'EOT'
            This is the name of the command that will be run.
            EOT);

        // TODO: validate $permission
        $permission = $parser->expectString("permission", "", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);

        $defaultOpOnly = $parser->expectBool("default-op", true, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = null; // TODO

        $dest = null; // TODO

        // TODO: Better Doc
        $rate = $parser->expectNumber("rate", 1, <<<'EOT'
            The Rate
            EOT);

        $minimumAmount = $parser->expectInt("minimum-amount", 0, <<<'EOT'
            The smallest amount of currency that can be transferred.
            EOT);

        $maximumAmount = $parser->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $transactionLabels = $parser->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $messages = null; // TODO

        return new CommandMethod($command, $permission, $defaultOpOnly, $src, $dest, $rate, $minimumAmount, $maximumAmount, $transactionLabels, $messages);
    }

    public static function writeDefaults(Parser $parser): void
    {
        $payMethod = $parser->enter("pay", "This is an example /pay method");
        
        $command = $payMethod->expectString("command", "pay", <<<'EOT'
            This is the name of the command that will be run.
            EOT);

        // TODO: validate $permission
        $permission = $payMethod->expectString("permission", "capital.transfer.pay", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);

        $defaultOpOnly = $payMethod->expectBool("default-op", false, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = null; // TODO

        $dest = null; // TODO

        // TODO: Better Doc
        $rate = $payMethod->expectNumber("rate", 1, <<<'EOT'
            The Rate
            EOT);

        $minimumAmount = $payMethod->expectInt("minimum-amount", 0, <<<'EOT'
            The smallest amount of currency that can be transferred.
            EOT);

        $maximumAmount = $payMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $transactionLabels = $payMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $messages = null; // TODO

        $takemoneyMethod = $parser->enter("takemoney", "This is an example /takemoney method");
        
        $command = $takemoneyMethod->expectString("command", "takemoney", <<<'EOT'
            This is the name of the command that will be run.
            EOT);

        // TODO: validate $permission
        $permission = $takemoneyMethod->expectString("permission", "capital.transfer.takemoney", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);

        $defaultOpOnly = $takemoneyMethod->expectBool("default-op", true, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = null; // TODO

        $dest = null; // TODO

        // TODO: Better Doc
        $rate = $takemoneyMethod->expectNumber("rate", 1, <<<'EOT'
            The Rate
            EOT);

        $minimumAmount = $takemoneyMethod->expectInt("minimum-amount", 0, <<<'EOT'
            The smallest amount of currency that can be transferred.
            EOT);

        $maximumAmount = $takemoneyMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $transactionLabels = $takemoneyMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $messages = null; // TODO

        $addmoneyMethod = $parser->enter("addmoney", "This is an example /addmoney method");
        
        $command = $addmoneyMethod->expectString("command", "addmoney", <<<'EOT'
            This is the name of the command that will be run.
            EOT);

        // TODO: validate $permission
        $permission = $addmoneyMethod->expectString("permission", "capital.transfer.addmoney", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);

        $defaultOpOnly = $addmoneyMethod->expectBool("default-op", true, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = null; // TODO

        $dest = null; // TODO

        // TODO: Better Doc
        $rate = $addmoneyMethod->expectNumber("rate", 1, <<<'EOT'
            The Rate
            EOT);

        $minimumAmount = $addmoneyMethod->expectInt("minimum-amount", 0, <<<'EOT'
            The smallest amount of currency that can be transferred.
            EOT);

        $maximumAmount = $addmoneyMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $transactionLabels = $addmoneyMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $messages = null; // TODO
    }
}
