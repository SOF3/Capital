<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use SOFe\Capital\Config\Parser;

class MethodFactory
{
    public static function build(Parser $parser, string $methodName) : Method
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
        $permission = $parser->expectString("permission", "capital.transfer.unspecified", <<<'EOT'
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

        $transactionLabels = null; // TODO

        $messages = Messages::parse($parser->enter("messages", <<<'EOT'
            These responses are sent depending on if an error occurred or
            if the transaction completed successfully.
            EOT));

        return new CommandMethod($command, $permission, $defaultOpOnly, $src, $dest, $rate, $minimumAmount, $maximumAmount, $transactionLabels, $messages);
    }

    public static function writeDefaults(Parser $parser) : void
    {
        $payMethod = $parser->enter("pay", "This is an example /pay method");

        $payMethod->expectString("command", "pay", <<<'EOT'
            This is the name of the command that will be run.
            EOT);

        $payMethod->expectString("permission", "capital.transfer.pay", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);

        $payMethod->expectBool("default-op", false, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = null; // TODO

        $dest = null; // TODO

        // TODO: Better Doc
        $payMethod->expectNumber("rate", 1, <<<'EOT'
            The Rate
            EOT);

        $payMethod->expectInt("minimum-amount", 0, <<<'EOT'
            The smallest amount of currency that can be transferred.
            EOT);

        $payMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $transactionLabels = null; // TODO

        $messages = null; // TODO

        $takemoneyMethod = $parser->enter("takemoney", "This is an example /takemoney method");

        $takemoneyMethod->expectString("command", "takemoney", <<<'EOT'
            This is the name of the command that will be run.
            EOT);

        $takemoneyMethod->expectString("permission", "capital.transfer.takemoney", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);

        $takemoneyMethod->expectBool("default-op", true, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = null; // TODO

        $dest = null; // TODO

        // TODO: Better Doc
        $takemoneyMethod->expectNumber("rate", 1, <<<'EOT'
            The Rate
            EOT);

        $takemoneyMethod->expectInt("minimum-amount", 0, <<<'EOT'
            The smallest amount of currency that can be transferred.
            EOT);

        $takemoneyMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $transactionLabels = null; //TODO

        $messages = null; // TODO

        $addmoneyMethod = $parser->enter("addmoney", "This is an example /addmoney method");

        $addmoneyMethod->expectString("command", "addmoney", <<<'EOT'
            This is the name of the command that will be run.
            EOT);


        $addmoneyMethod->expectString("permission", "capital.transfer.addmoney", <<<'EOT'
            This is the permission players must have.
            It will be created for you.
            EOT);

        $addmoneyMethod->expectBool("default-op", true, <<<'EOT'
            This requires the user of the command to have op permissions.
            EOT);

        $src = null; // TODO

        $dest = null; // TODO

        // TODO: Better Doc
        $addmoneyMethod->expectNumber("rate", 1, <<<'EOT'
            The Rate
            EOT);

        $addmoneyMethod->expectInt("minimum-amount", 0, <<<'EOT'
            The smallest amount of currency that can be transferred.
            EOT);

        $addmoneyMethod->expectInt("maximum-amount", 0, <<<'EOT'
            The largest amount of currency that can be transferred.
            EOT);

        $transactionLabels = null; // TODO

        $messages = null; // TODO
    }
}
