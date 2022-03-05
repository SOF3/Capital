<?php

require __DIR__ . "/common.php";

return function() {
    $context = new Context;

    yield from init_steps($context);
    yield from add_money_test($context, "alice", "bob", 10, 110);
    yield from take_money_test($context, "alice", "alice", 15, 85);
    yield from pay_money_test($context, "alice", "bob", 3, 82, 113);
    yield from check_self_money($context, "bob", 113);
    yield from check_other_money($context, "alice", "bob", 113);
    yield from check_top_money($context, "bob", [
        'Showing page 1 of 1',
        '#1 bob: $113',
        '#2 alice: $82',
    ]);
};
