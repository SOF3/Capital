# Developer notes

This document is for developers who want to contribute to this project.

## Project structure and patterns

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
