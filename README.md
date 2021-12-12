# Capital
A simple but very extensible economy engine for PocketMine-MP.

## For Developers
Similar to configs,
the developer API exposes three variants:
the basic API, advanced API and expert API.

### Basic API
Similar to the basic config,
the basic API only provides access to one account per player.
This API might not work if the user is using advanced config
and removed the default currency.

#### Add/Reduce money

### Advanced API
Similar to the advanced config,

### Expert API
The expert API provides full access to the 

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
| `capital/player/uuid` | The player UUID |
| `config/currency` | `default` |
| `capital/player/name` | The player name |
| `capital/player/infoName` | `money` |
| `capital/core/valueMin` | `0` |
| `capital/core/valueMax` | `1000000` |

This is how we find the accounts for a player:
we find accounts with the label `capital/player/uuid`
equal to the player's UUID,
then we use other labels like `config/currency`
to identify which account it is.

The fun part here is, we don't actually care which account it is.
Capital itself does not know what `config/currency` is;
it's just the default label we use for currencies
when we transform the advanced config into expert config.
If you don't need compatibility with the Advanced API,
it's perfectly fine to delete the `config/currency` label.

The first two labels are "identifying" labels.

