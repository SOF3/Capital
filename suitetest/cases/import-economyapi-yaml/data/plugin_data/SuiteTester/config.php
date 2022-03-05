<?php

require __DIR__ . "/common.php";

return function() {
    $context = new Context;

    yield from init_steps($context);

    yield "execute migration" => function() use($context) {
        $alice = $context->server->getPlayerExact("alice");
        $context->server->dispatchCommand($alice, "capital-migrate");
        yield from $context->awaitMessage($alice, "Migration completed. Imported 2 accounts.");
    };

    yield from pay_money_test($context, "alice", "bob", 1, 122, 457);
};
