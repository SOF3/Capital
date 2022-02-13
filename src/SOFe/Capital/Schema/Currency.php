<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use pocketmine\player\Player;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\LabelSelector;
use SOFe\Capital\LabelSet;

use function array_keys;
use function count;
use function implode;

/**
 * A schema where each player has one account for each currency.
 */
final class Currency implements Schema {
    public const LABEL_CURRENCY = "capital/currency";


    public static function build(Parser $globalConfig) : self {
        $currenciesParser = $globalConfig->enter("currencies", <<<'EOT'
            All currencies used on your server.
            EOT);
        $currencyNames = $currenciesParser->getKeys();
        if (count($currencyNames) === 0) {
            $currenciesParser->failSafe(null, "There must be at least one currency");
            $currencyNames[] = "money";
            $currenciesParser->enter("money", <<<'EOT'
                This is an example currency called \"money\".
                You can rename this by changing the word \"money\".
                You can also add new currencies by duplicating the whole block below.
                EOT);
        }

        $currencies = [];
        foreach ($currencyNames as $currency) {
            $currencyParser = $currenciesParser->enter($currency, "this string should never appear");
            $currencies[$currency] = AccountConfig::parse($currencyParser);
        }

        $term = $globalConfig->expectString("term", "currency", <<<'EOT'
            The name of the command argument for the currency.
            Also used in forms when to select the currency.
            EOT);

        $defaultCurrency = $globalConfig->expectNullableString("default-currency", null, <<<'EOT'
            The default currency to use in commands if the user does not specify one.
            If this is set to ~, the user is required to specify the currency for every command.
            This option can be overridden in the config for individual commands.
            EOT);

        return new self($currencies, $term, $defaultCurrency);
    }

    public static function describe() : string {
        return "Each player has one account for each currency.";
    }

    /**
     * @param array<string, AccountConfig> $currencies The currencies allowed in the current clone.
     */
    public function __construct(
        private array $currencies,
        private string $currencyTerm,
        private ?string $defaultCurrency = null,
    ) {
    }

    public function clone() : self {
        return clone $this;
    }

    public function cloneWithConfig(Parser $config) : self {
        $clone = clone $this;

        $defaultCurrency = $config->expectNullableString("default-currency", null, <<<'EOT'
            The default currency to use in commands if the user does not specify one.
            If this is set to ~, the user is required to specify the currency for every command.
            This option can be overridden in the config for individual commands.
            EOT, false);
        if (!isset($clone->currencies[$defaultCurrency])) {
            $defaultCurrency = $config->failSafe(null, "default-currency must be one of " . implode(", ", array_keys($clone->currencies)));
        }
        $clone->defaultCurrency = $defaultCurrency;

        $allowedCurrencies = $config->expectNullableStringList("allowed-currencies", null, <<<'EOT'
            The list of currencies the user can select from.
            EOT, false);
        if ($allowedCurrencies !== null) {
            $clone->currencies = [];

            foreach ($allowedCurrencies as $i => $currency) {
                if (!isset($this->currencies[$currency])) {
                    $config->failSafe(null, "Item #" . ($i + 1) . " in allowed-currencies is not one of " . implode(", ", array_keys($this->currencies)));
                }

                $clone->currencies[$currency] = $this->currencies[$currency];
            }
        }

        return $clone;
    }

    public function cloneWithCompleteConfig(Parser $config) : Complete {
        $clone = clone $this;

        $currency = $config->expectString("currency", array_keys($clone->currencies)[0], <<<'EOT'
            The currency to use.
            EOT);
        if (!isset($clone->currencies[$currency])) {
            $currency = $config->failSafe(array_keys($clone->currencies)[0], "default-currency must be one of " . implode(", ", array_keys($clone->currencies)));
        }

        $clone->defaultCurrency = $currency;
        $clone->currencies = [$currency => $this->currencies[$currency]];

        return new Complete($clone);
    }

    /**
     * @return Variable<self, string>
     */
    private function createCurrencyVariable() : Variable {
        return new Variable(
            type: Variable::TYPE_STRING,
            name: $this->currencyTerm,
            populate: fn(Currency $schema, $currency) => $schema->defaultCurrency = $currency,
            enumValues: array_keys($this->currencies),
        );
    }

    public function isComplete() : bool {
        return $this->defaultCurrency !== null;
    }

    public function getRequiredVariables() : iterable {
        if ($this->defaultCurrency === null && count($this->currencies) > 1) {
            yield $this->createCurrencyVariable();
        }
    }

    public function getOptionalVariables() : iterable {
        if ($this->defaultCurrency !== null && count($this->currencies) > 1) {
            yield $this->createCurrencyVariable();
        }
    }

    public function getSelectedAccountConfig() : ?AccountConfig {
        if ($this->defaultCurrency === null) {
            return null;
        }

        return $this->currencies[$this->defaultCurrency];
    }

    public function getSelector(Player $player) : ?LabelSelector {
        $defaultCurrency = $this->defaultCurrency;
        if ($defaultCurrency === null) {
            return null;
        }

        return new LabelSelector([
            AccountLabels::PLAYER_UUID => $player->getUniqueId()->toString(),
            self::LABEL_CURRENCY => $defaultCurrency,
        ]);
    }

    public function getOverwriteLabels(Player $player) : ?LabelSet {
        return $this->getSelectedAccountConfig()?->getOverwriteLabels($player);
    }

    public function getMigrationSetup(Player $player) : ?MigrationSetup {
        return $this->getSelectedAccountConfig()?->getMigrationSetup($player);
    }

    public function getInitialSetup(Player $player) : ?InitialSetup {
        $defaultCurrency = $this->defaultCurrency;
        if ($defaultCurrency === null) {
            return null;
        }

        return $this->getSelectedAccountConfig()
            ?->getInitialSetup($player)
            ?->andInitialLabel(new LabelSet([
                self::LABEL_CURRENCY => $defaultCurrency,
            ]));
    }
}
