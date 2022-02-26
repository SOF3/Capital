# Capital

[![CI](https://github.com/SOF3/Capital/actions/workflows/ci.yml/badge.svg)](https://github.com/SOF3/Capital/actions/workflows/ci.yml)

An extensible economy plugin for PocketMine-MP.

## How is Capital different from other economy plugins?

As a core API for economy, Capital supports different styles of account management:

- You can have the old, simple one-account-per-player mechanism.
- Or do you like currencies? You can add new currencies to config.yml
  and other plugins will let you configure which currency to use in each case.
- Or are currencies too complicated for you?
  What about just having one account per world?
  You don't need any special configuration in other plugins!
- Are commands and form UI boring for you?
  Maybe use banknote/wallet items
  so that players lose money when they drop the item?
  (Capital itself does not support banknote/wallet items,
  but it is the *only* economy API where both
  simple accounts and item payment can be used from other plugins
  without writing code twice)

Other cool features include:

- Powerful analytics commands.
  How much active capital is there?
  How is wealth distributed on the server?
  Which industries are the most active?
  What are the biggest transactions in the server yesterday?
  Capital can help you answer these questions with label-based analytics.
- Is editing the config file too confusing for you?
  Capital supports self-healing configuration.
  Your config file will be automatically regenerated if something is wrong,
  and Capital will try its best to guess what you really wanted.
- Supports migration from other economy plugins, including:
  - EconomyAPI
- Uses async database access, supporting both SQLite and MySQL.
  Capital will not lag your server.
- Safe for multiple servers. Transactions are strictly atomic.
  Players cannot duplicate money by joining multiple servers.

## Contributing

See [dev.md](dev.md) if if you want to get started with developing Capital.
