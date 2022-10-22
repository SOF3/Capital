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
- Or maybe sometimes the money goes to the faction bank account
  instead of player account?
- Capital is extensible for other plugins to include new account management styles,
  and it will work automatically with all plugins!

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

## Setting up

After running the server with Capital the first time,
Capital generates config.yml and db.yml,
which you can edit to configure Capital.

db.yml is used for configuring the database used by Capital.
You can use sqlite or mysql here.
The configuration is same as most other plugins.

config.yml is a large config that allows you to change almost everything in Capital.
Read the comments in config.yml for more information.
Text after `'# xxx:` are comments.
If you edit config.yml incorrectly,
Capital will try to fix the config.yml and save the old one as config.yml.old
so that you can refer to it if Capital fixed it incorrectly.

## Default commands

All commands in Capital can be configured in config.yml.
Try searching them in the config file to find out the correct place.
The following commands come from the default config:

Player commands:

- `/pay <player> <amount> [account...]`:
  Pay money to another player with your own account.
- `/checkmoney`:
  Check your own wealth.
- `/richest`:
  View the richest players on the server.

Admin commands:

- `/addmoney <player> <amount> [account...]`:
  Add money to a player's account.
- `/takemoney <player> <amount> [account...]`:
  Remove money from a player's account.
- `/checkmoney <player>`:
  Check the wealth of another player.

`[account...]` can be used to select the account (e.g. currency)
if you change the schema in config.yml.
(You can still disable these arguments by setting up `selector` in config.yml)

You can create many other useful commands by editing config.yml,
e.g. check how much money was paid by `/pay` command!
Check out the comments in config.yml for more information.

## Community, Contact &amp; Contributing

If you want to get help, share your awesome config setup
or show off your cool plugin that uses Capital,
create a discussion [on GitHub](https://github.com/SOF3/Capital/discussions).

To report bugs, create an issue [on GitHub](https://github.com/SOF3/Capital/issues).

If you want to help with developing Capital,
see [dev.md](dev.md) for a comprehensive walkthrough of the internals.
