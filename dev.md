# Developer notes

This document is for developers who want to contribute to this project.
It explains the project structure and some code style requirements.

## Async functions

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

## Module system and dependency injection

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

## Config

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

## Database

Capital uses [libasynql](https://github.com/poggit/libasynql) for database connection.
Note that the libasynql DataConnector is exposed in the Database API,
whcih means the SQL definition is part of semantic versioning.
All structural changes are considered as backward-incompatible changes.
The `Database` class also provides some low-level
(although higher level than raw SQL queries) APIs to access the database.
Other modules should consider using the APIs in the `SOFe\Capital\Capital` singleton,
which provides more user-friendly and type-safe APIs than the `Database` class.

## Labels

<!-- TODO -->

## Schema

<!-- TODO -->

## Cache

<!-- TODO -->

## Analytics

<!-- TODO -->

## Transfer

<!-- TODO -->

## Integration testing

<!-- TODO -->
