# Capital
[![CI](https://github.com/SOF3/Capital/actions/workflows/ci.yml/badge.svg)](https://github.com/SOF3/Capital/actions/workflows/ci.yml)

A simple but very extensible economy plugin for PocketMine-MP.

## How is Capital different from other economy plugins?
Capital introduces a label-oriented paradigm,
allowing Capital to be extensible for many purposes:

- Currencies are no longer imposed by the core.
  You don't need to deal with currencies if you don't want to,
  and you can create as many currencies as you like without breaking things.
- The planned Wallet extension allows connecting inventory items to player accounts,
  and this is automatically compatible with other plugins that are not even aware of wallets.
- Analytics can be easily achieved using label-based queries.
  Track how capital flows in your server without writing code!

## Labels
Capital uses *labels* to identify accounts.
In a nutshell, every account has:

- a UUID (which has nothing to do with the player UUID)
- a balance value (signed 64-bit integer)
- a key-value map of string labels

Labels are used to store metadata about an account,
and can also be used for searching.
For example, the default player account (in basic config) has the following labels:

| Name | Value |
| :---: | :---: |
| `capital/playerUuid` | The player UUID |
| `currency` | `default` |
| `capital/playerName` | The player name |
| `capital/playerInfoName` | `money` |
| `capital/valueMin` | `0` |
| `capital/valueMax` | `1000000` |

This is how we find the accounts for a player:
we find accounts with the label `capital/player/uuid`
equal to the player's UUID,
then we use other labels like `currency`
to identify which account it is.

In fact, Capital itself does not know what `currency` is;
it's just the default label we use for currencies,
but you can change it to anything.
It is perfectly fine to delete the `currency` label.

Plugins that perform Capital transactions should
prefer using labels to select accounts for transactions.
For example, the pay command is implemented as
two label selectors for the source and destination accounts,
parameterized with InfoAPI to change labels by players.

## Building
On Linux, the phar can be built simply by running `make dev/Capital.phar`.

## Testing
Capital uses integration testing.
Run `make suites` to run all integration tests.

To rerun a test without resetting MySQL,
set `REUSE_MYSQL=true` for the make recipe.

To interact iwth the MySQL database
(it is persisted until the next time a suite is run without `REUSE_MYSQL=true`),
run `make debug/suite-mysql`.
