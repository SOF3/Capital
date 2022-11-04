# Developer notes

## High-level API

This section describes the simple API for developers of other plugins
without learning the concepts of Capital in depth.

Capital supports different ways of classifying accounts (known as [schemas](#schema)),
e.g. multi-currency and multi-world.
The good news is, you don't have to consider each schema,
because Capital will figure it out.
You just need to leave a place in your config.yml
so that users who want to use other schemas can configure it:

```yaml
# Selects the Capital account to use.
# See https://github.com/SOF3/Capital/wiki/Schemas for more details.
selector: {}
```

Users can fill this selector with options like `allowed-currencies` etc.,
depending on the schema they chose.

In `onEnable`, store this in a class property:

```php
use SOFe\Capital\{Capital, CapitalException, LabelSet};

class Main extends PluginBase {
  private $selector;

  protected function onEnable() : void {
    Capital::api("0.1.0", function(Capital $api) {
      $this->selector = $api->completeConfig($this->getConfig()->get("selector"));
    });
  }
}
```

Then you can use the stored selector in the code
where you want to manipulate player money.

### Take money from a player

Let's make a plugin called "ChatFee"
which charges the player $5 for every chat message:

```php
public function onChat(PlayerChatEvent $event) : void {
  $player = $event->getPlayer();

  Capital::api("0.1.0", function(Capital $api) use($player) {
      try {
        yield from $api->takeMoney(
          "ChatFee",
          $player,
          $this->selector,
          5, 
          new LabelSet(["reason" => "chatting"]), 
        );

        $player->sendMessage("You lost $5 for chatting");
      } catch(CapitalException $e) {
        $player->kick("You don't have money to chat");
      }
  });
}
```

The first "ChatFee" is your plugin name
so that server admins can track which plugin gave the money.
Capital will create a system account for your plugin,
and the money will actually go into the ChatFee system account.

The second parameter `$player` tells Capital which player to take money from.

The third parameter `$this->selector` tells Capital which account to take money from,
as we have explained in the previous section.
Note: if you have multiple different scenarios of giving/taking money,
consider using different selectors.

The fourth parameter `5` is the amount of money to take.
This value must be an integer.

The last parameter `new LabelSet(["reason" => "chatting"])`
provides the labels for the transaction.
Server admins can use these labels to perform analytics.
You may want to let users configure these labels in the config too.

The try-catch block lets you handle the scenario where
player does not have enough money to be taken.
However, remember that **you cannot cancel `$event` after the first `yield`**,
because transactions are asynchronous, which means that
the event already happened by that time and it is too late to cancel.

### Giving money to a player

Giving money is similar to taking money,
except `takeMoney` becomes `addMoney`.
Let's make a plugin called "HitReward" that
gives the player money when they attack someone:

```php
public function onDamage(EntityDamageByEntityEvent $event) : void {
  $player = $event->getDamager();
  if(!$player instanceof Player) {
    return;
  }

  Capital::api("0.1.0", function(Capital $api) use($player) {
    try {
      yield from $api->addMoney(
        "HitReward",
        $player,
        $this->selector,
        5, 
        new LabelSet(["reason" => "attacking"]),
      );

      $player->sendMessage("You got $5");
    } catch(CapitalException $e) {
      $player->sendMessage("You have too much money!");
    }
  });
}
```

### Paying money from a player to another

Paying money is like taking money from one player and giving to another,
but it only happens when *both* players have enough money and don't exceed limits.
Neither player will lose or receive money if any limits are violated.

```php
public function pay(Player $player1, Player $player2) : void {
  Capital::api("0.1.0", function(Capital $api) use($player1, $player2) {
    try {
      yield from $api->pay(
        $player1,
        $player2,
        $this->selector,
        5, 
        new LabelSet(["reason" => "payment"]),
      );

      $player1->sendMessage("You paid $5 to " . $player2->getName());
    } catch(CapitalException $e) {
      $player1->sendMessage("Failed!");
    }
  });
}
```

All arguments are same as before,
except you don't need to pass your plugin name
because the money came from a player and you don't need a plugin account.

If the payer and receiver sides have different amounts (e.g. service fee),
you have to use `payUnequal` instead.
The following code makes `$player1` pay `$player2` $5,
plus giving $3 service fee to the "ServiceFee" system account:

```php
public function pay(Player $player1, Player $player2) : void {
  Capital::api("0.1.0", function(Capital $api) use($player1, $player2) {
    try {
      yield from $api->payUnequal(
        "ServiceFee",
        $player1,
        $player2,
        $this->selector,
        5 + 3, // this is the total amount that $player1 has to lose
        5, // this is the total amount that $player2 gets
        new LabelSet(["reason" => "payment"]),
        new LabelSet(["reason" => "service-fee"]), // this label set is applied on the transaction from $player1 to the system account
      );

      $player1->sendMessage("You paid $5 to " . $player2->getName() . " and paid $3 service fee");
    } catch(CapitalException $e) {
      $player1->sendMessage("Failed!");
    }
  });
}
```

It is also possible for player1 to pay less and player2 to pay more.
In that case, player1 only pays the amount to player2,
then the system account will pay the rest to player2.

### Getting money for a player

If you want to check whether the player has enough money for something,
use `takeMoney` as explained above and handle the error case.

If you just want to display player money, use InfoAPI.
The default config registered the `{money}` info on players,
but users can change this based on their config setup.
Consider using InfoAPI to compute the messages
and let the user set their own messages.
See [InfoAPI readme](https://github.com/SOF3/InfoAPI) for usage guide.

## API

### Async functions

Capital uses [await-generator](https://github.com/SOF3/await-generator),
which enables asynchronous programming using generators.
Functions that return `Generator<mixed, mixed, mixed, T>`
are async functions that return values of type `T`.
There is a special phpstan-level type alias `VoidPromise`,
which is just shorthand for `Generator<mixed, mixed, mixed, void>`.

Generator functions must always be called with `yield from` instead of `yield`.
Functions that delegate to another generator function MUST always
`return yield from delegate_function();`,
even though `return delegate_function();` and
`return yield delegate_function();` have similar semantics.
This is to ensure consistent behavior where
async functions only start executing when passed to the await-generator runtime.

### Module system and dependency injection

Capital is module-based.
Every module containing a `Mod` class is a module.
Each module has its independent semantic versioning line,
as indicated by the `API_VERSION` constant.
The `Mod` class is responsible for starting and stopping
components that cannot wait to be initialized only on-demand,
such as commands, event handlers and InfoAPI infos/fallbacks.

Classes that only have one useful instance in the runtime are called "singletons".
To facilitate unit testing in the future,
singletons do not use the traditional `getInstance()` style.
Instead, all singletons are managed by the `Di` namespace.
If a (singleton or non-singleton) class requires an instance of a singleton class,
it can implement the `Di\FromContext` interface and use the `Di\SingletonArgs` trait,
then declare all required classes in the constructor, for example:

```php
use SOFe\Capital\Di;

class Foo implements Di\FromContext {
  use Di\SingletonArgs;

  public function __construct(
    private Bar $bar,
    private Qux $qux,
  )
}
```

Alternatively, if initialization of the object is async
(e.g. `Database\Database` initialization requires waiting for table creation queries),
or the developer does not wish to mix initialization logic in the constructor
(it is a bad practice to do anything
other than field initialization and validation in the constructor),
declare `public static function fromSingletonArgs` in a similar style.
`fromSingletonArgs` can return either `self` or `Generator<mixed, mixed, mixed, self>`.

The class can be instantiated with `Foo::instantiateFromContext(Di\Context $context)`.
Alternatively, if `Foo` itself is a singleton class,
it can additionally implement `Di\Singleton` and use the trait `Di\SingletonTrait`,
then it can be requested from another `FromContext` class with the same style.

All `Mod` classes are singleton and use `Di\SingletonArgs`.
They are required in the `Loader\Loader` (this is *not* the main class) singleton,
which is explicitly created from the main class `onEnable`.
<!-- TODO setup GitHub CI to generate and link to depgraph.svg -->

The main class (`Plugin\MainClass`) is a singleton,
although it is not initialized lazily like other FromContext classes
(since it is the same instance that started loading everything).
Note that, unlike many other plugins,
the main class does not have any functionality by itself.
It serves only to implement the `Plugin` interface
as required by some PocketMine APIs,
and is generally useless except for registering commands and event handlers.

The `Di\Context` is also a singleton,
but similar to the main class, it is not initialized lazily.
Other classes can use it to flexibly require new objects that
were not requested in the constructor under special circumstances.

The await-std instance (`\SOFe\AwaitStd\AwaitStd`) does not implement `Di\Singleton`,
but it is also special-cased to allow singleton-like usage.

The `\Logger` interface is not a singleton.
However, `FromContext` classes can declare a parameter of type `\Logger`,
then the DI framework will create a new logger for the class.
(This logger is derived from the plugin logger,
but is not equal to the plugin logger itself)

### Config

Capital implements a self-healing config manager.
Each module has its own `Config` class to manage module-specific configuration.
In addition to the singleton and FromContext interfaces,
each `Config` class also implements `Config\ConfigInterface` and uses `Config\ConfigTrait`,
implementing a `parse` method that reads values
from a `Config\Parser` object into itself.

The first time `parse` is called, `Config\Parser` is in non-fail-safe mode,
which means methods like `expectString` would throw a `Config\ConfigException`
if the parsed config contains invalid types or data.
Upon catching a `Config\ConfigException`,
the config framework calls `parse` on all `Config` classes again,
this time providing a `Config\Parser` in fail-safe mode,
which would no longer throw `Config\ConfigException`.
Instead, the parser will add missing fields (along with documentation)
or replace incorrect fields in the config,
which are saved to the config after all module configs have been parsed.
`Config` classes can also use the `failSafe` method in the `Config\Parser`
to *either* return a value *or* throw a `Config\ConfigException`
depending on the parser mode.
This strategy allows automatic config refactor when the user changes critical settings
like the [schema](#schema), which cascades changes to many other parts in the config.

Due to difficulties with cyclic dependencies,
all `Config` classes must be separated listed in the `Config\Raw::ALL_CONFIG` constant.

### Database

Capital uses [libasynql](https://github.com/poggit/libasynql) for database connection.
Note that the libasynql DataConnector is exposed in the Database API,
whcih means the SQL definition is part of semantic versioning.
All structural changes are considered as backward-incompatible changes.
The `Database` class also provides some low-level
(although higher level than raw SQL queries) APIs to access the database.
Other modules should consider using the APIs in the `SOFe\Capital\Capital` singleton,
which provides more user-friendly and type-safe APIs than the `Database` class.

Raw queries are written in [resources/mysql](resources/mysql)
and [resources/sqlite](resources/sqlite).
There is a slight diversion in MySQL and SQLite queries due to different requirements;
SQLite does not require any synchronization and assumes FIFO query execution.
MySQL assumes there may be multiple servers using the database,
plus external services (such as web servers) that may modify the data arbitrarily.

### Labels

The Capital database is gameplay-agnostic.
This means the database design is independent of how accounts are created.
The database module does not know anything about players or currencies or worlds.
Instead, each account is attached with *labels* (a string-to-string map),
which provide information about the account and enable account searching.

Each player may have zero or more accounts,
determined by the [schema](#schema) configured.
Generally speaking, player accounts are identified by the `capital/playerUuid` label
(or `capital/playerName` if username display is required);
analytics modules can use this label to identify accounts associated to a player.

Capital aims to provide reproducible transactions.
All account balance changes other than initial account creation
should be performed through a transaction.
In the case of balance change as initiated by an operator
or automatic reward provided by certain gameplay (e.g. kill reward),
a system account (known as an "oracle") should be used as the payer/payee.
Oracles do not have player identification labels like `capital/playerUuid`,
but they use the `capital/oracle` to identify themselves.

Other modules/plugins can also define other account types.
As long as they have their own labels that do not collide with existing labels,
they are expected to work smoothly along with other components.

Transactions can also have their own labels.
At the current state, the exact usage of transaction labels is not confirmed yet.
It is expected that transaction labels can be used to analyze economic activity,
such as the amount of capital flow in certain industries.

The database can search accounts and transactions matching a "label selector",
which is an array of label names to values.
Accounts/transactions are matched by a selector if
they have all the labels specified in the selector.
An empty value in a label selector matches all accounts/transactions
with that label name regardless of the value.

### Schema

A schema abstracts the concept of labels
by expressing them in more user-friendly terminology.
A schema is responsible for defining the accounts for a player
and parsing config into a specific account selector.

There are currently two builtin schema types:

- `basic`: Each player uses the same account for everything.
- `currency`: Currencies are defined in schema config,
  where each player has one account for each currency.

There are other planned schema types, which impose speical challenges:

- `world`: Each player has one account for each world.
  This means accounts must be created lazily and dynamically,
  because new worlds may be loaded over time.
- `wallet`: Accounts are bound to inventory items instead of players.
  Players can spend money in an account
  when the item associated with the account is in the player's inventory.
  This means the player label is mutable and requires real-time updating.

Let's explain how schemas work with a payment command and a currency schema.
The default schema is configured as:

```yaml
schema:
  type: currency
  currencies:
    coins: {...}
    gems: {...}
    tokens: {...}
```

The payment command is configured with a section like this:

```yaml
accounts:
  allowed-currencies: [coins, tokens]
```

This config section is passed to the default schema,
which returns a new `Schema` object that
only contains the currency subset `[coins, tokens]`.

When a player runs the payment command (e.g. `/pay SOFe 100 coins`),
the remaining command arguments (`["coins"]`)
are passed to the subset schema,
which decides to parse the first argument as the currency name.
Since we only use the subset schema,
only coins and tokens are accepted.
The subset schema returns a final `Schema` object
that knows `coins` have been selected.
The sender and recipient are passed to the final `Schema`,
which returns a label selector for the sender and recipient accounts.
If no eligible accounts are found,
the plugin tries to migrate the accounts
from imported sources as specified by the schema.
If no migration is eligible,
it creates new accounts based on initial setup specified by the schema.

### Analytics

The Analytics module consists of two parts: Single and Top.

### Single metrics

`Analytics\Single` computes single-value metrics.

The `Analytics\Single\Query` interface abstracts different metric types
parameterized by a generic parameter `P`.
Use `CachedValue::loop` to spawn a refresh loop that fetches the latest metric value.
If `P` is `Player`, use `PlayerInfoUpdater::registerListener()`
to automatically spawn refresh loops for online players.

### Top metrics

`Analytics\Top` reports server-wide top metrics.

Due to the label-oriented mechanism,
it is not possible to efficiently fetch the top accounts directly
because the SQL database cannot be indexed by a specific label.
To allow efficient top metric queries,
the metric is first computed for each grouping label value
(usually the player UUID) and cached in the `capital_analytics_top_cache` table.

A top metric query is defined by the following:

- The aggregator to use.
  This also defines whether the query operates on accounts or transactions.
  Currently all aggregators are accounts-only or transactions-only,
  but there will be aggregators on transactions for each account in the future.
- The label selector that filters rows.
  For example, if the aggregator is about number of transactions of each player,
  the label selector filters away non-player accounts
  (it is not a transaction label selector).
- The grouping label name, where its values will be used to group rows.
  For queries on top players, this is `AccountLabels::PLAYER_UUID`.

These three values uniquely identify a top query for computation cache.
These values are md5-hashed into the `capital_analytics_top_cache.query` column,
which are reused on multiple servers.
The computation takes place in batches, updating a subset of label values each time.
Call `Analytics\Top\Mod::runRefreshLoop()` to start a refreshing loop.

`Analytics\Top\DatabaseUtils::fetchTopAnalytics` fetches the cached data for display.
For each `displayLabels` label, a random label value for matching rows
with the name equal to the `displayLabels` label is returned in the output for display.

### Transfer

<!-- TODO -->

### Migration

<!-- TODO -->

### Archiving

<!-- TODO -->

### CapiTrade

CapiTrade is the shops implementation for Capital.
It exposes a generic event-based API,
so in a sense, using a CapiTrade shop is like "buying an event".

#### Shop labels

Shop data can be stored in labels, similar to account/transaction labels.
In the case of shops, labels are mostly intended for technical use
and it is not recommended to expose shop labels to the user interface.

If there are special requirements,
shop data can still be stored on external tables or storages, e.g. entity/items,
but in general, shop labels are better for storage
because they allow synchronous shop admission in shop event handlers.

#### Shop accessors

The same shop can be accessed with multiple methods.
Each method is called a "shop accessor".
A shop is considered inaccessible when the last accessor is deleted,
in which case the shop itself (along with its labels) also get deleted.

Examples of shop accessors include:

- Using certain commands
- Clicking blocks at specific positions
- Using certain items
- Clicking on certain entities
- Using an NPC trading dialog

#### Shop price

The shop price is a first-class data value stored in the shop table directly.
Shop prices can be adjusted by control loops in servers
that may depend on e.g. metrics computed from accounts/transactions.

Shop prices may be positive or negative.
The peer of the shop transaction is stored as
an account label selector in the shop label `capitrade/peer-account-selector`.

#### Shop executors

Shop executors are the components that implement the outcome of a shop.
Examples of shop executors include:

- Adding/removing inventory items (a.k.a. buying/selling items)
- Adding/removing player effects
- Updating a player's kits

When a player accesses a shop, CapiTrade dispatches a `ShopExecuteEvent`.
Shop executors should check the shop labels in the event
to determine the desired effects of this shop.

Shop executors can admit or deny an access event.
Denial takes place when the prerequisites of the executor are not satisfied.
For example, an item-selling executor denies an access event
if the player does not have the required items in their inventory.

When a shop executor admits an access event,
they should not execute the outcome directly.
Instead, they should try to *hold* the prerequisites,
i.e. ensure that the checked prerequisites will remain satisfied for a short period
(e.g. deny the player from removing the items from their inventory).
After Capital executes the transaction,
it will notify the executors that the transaction succeeded or failed,
in which case the executors should actually execute the outcome
or release the held prerequisites (e.g. stop denying item removal).

## Tooling

Capital is developed on Linux.
All development pipelines are stored in the [Makefile](Makefile).

### Building the plugin

The phar output and intermediate files (such as libraries)
are stored in the `dev/` directory (which is git-ignored).

To build the plugin, run

```shell
make dev/Capital.phar
```

### Static analysis

The project is strictly phpstan-compliant.
To analyze the project with phpstan, run

```shell
make phpstan
```

Please `make phpstan` before every commit.
If all outstanding phpstan errors should be ignored,
regenerate the baseline file with

```shell
make phpstan-baseline.neon/regenerate
```

### Formatting

Please reformat the code and reorganize imports before every commit with the command

```shell
make fmt
```

### Integration testing

An integration testing starts a clean PocketMine server with Capital installed
and interacts with Capital to check if it behaves as expected.
Integration tests are managed in the [`suitetest/`](suitetest/) directory.

Integration testing is performed using Docker.
Please [install Docker](https://docs.docker.com/get-docker/) first.

Each integration test case (a "test suite")
is declared by a new directory under [`suitetest/cases/`](suitetest/cases/)
with the following structure:

```text
suitetest/cases/*
├── data
│   └── plugin_data
│       ├── Capital
│       │   └── (Capital config files...)
│       └── SuiteTester
│           └── config.php
├── expect-config.yml (optional)
├── options
│   └── skip-mysql (optional)
└── output (generated)
    ├── actual-config.yml
    ├── depgraph.dot
    ├── depgraph.svg
    └── output.json
```

`data` contains files to be mounted onto `/data`
inside the test container running `pmmp/pocketmine-mp`.
The files are merged with [`suitetest/shared/data/`](suitetest/shared/data/)
to share common configuration.

If `expect-config.yml` is present,
the suite test fails if it is not identical to
the actual config.yml generated by the plugin during the test.

If `options/skip-mysql` is absent,
the script will spawn a new MySQL container and wait for it to be ready
before starting the PocketMine container.

The `output` directory is generated by the script after the suite test completes.
`output/output.json` contains a JSON file reporting how many test steps passed.
`depgraph.dot` (and `depgraph.svg` if the GraphViz `dot` command is found)
renders a dependency graph for the singleton objects
and displays their initialization timestamp.
`actual-config.yml` is the config file generated after the server stops.

To run all test suites, run

```shell
make suitetest
```

To run a single test suite, run

```shell
make suitetest/cases/${TEST_SUITE_NAME}
```

To open MySQL queries on the `mysql` test case, run

```shell
make debug/suite-mysql
```
