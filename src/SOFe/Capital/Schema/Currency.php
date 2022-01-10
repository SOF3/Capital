<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config\ConfigException;
use function in_array;

/**
 * A schema where each player has one account for each currency.
 *
 * @implements Schema<CurrencyVars>
 */
final class Currency implements Schema {
    public const LABEL_CURRENCY = "capital/currencySchema/currency";

    /** @var string|null The default currency in command argument */
    private ?string $defaultCurrency = null;
    /** @var list<string>|null The list of currencies allowed in the current clone */
    private ?array $allowedCurrencies = null;

    public static function build(array $globalConfig) : self {
        if(!isset($globalConfig["currencies"])) {
            throw new ConfigException("schema.currencies missing");
        }

        $currencies = $globalConfig["currencies"];
        $term = $globalConfig["term"] ?? "Currency";
        $migrateUnassigned = $globalConfig["migrate-unassigned"] ?? null;

        if(!in_array($migrateUnassigned, $currencies, true)) {
            throw new ConfigException("schema.migrate-unassigned must be one of the currencies specified in schema.currencies");
        }

        return new self($currencies, $term, $migrateUnassigned);
    }

    public static function infer(array $inferConfig) : self {
        $currencies = $inferConfig["currencies"] ?? ["money"];
        $migrateUnassigned = $currencies[0];
        if(isset($inferConfig["migrate-unassigned"])) {
            // migrate-unassigned probably failed because schema.migrate-unassigned hda an invalid value.
        }

        return new self(
            $currencies,
            $inferConfig["term"] ?? "Currency",
            $migrateUnassigned,
        );
    }

    /**
     * @param list<string> $currencies
     */
    public function __construct(
        private array $currencies,
        private string $currencyTerm,
        private string $migrateUnassigned,
    ) {}

    public function getConfig() : array {
        return [
            "##" => <<<'EOT'
                In the currency schema, each player has one account for each currency.
                EOT,

            "#currencies" => "The list of currencies to use",
            "currencies" => $this->currencies,

            "#migrate-unassigned" => <<<'EOT'
                If the player previously had an account from the basic schema or migrated from a plugin without currencies,
                the original account will be assigned to the currency specified here.
                If you do not want to migrate the original account, set this to ~.
                EOT,
            "migrate-unassigned" => $this->migrateUnassigned,

            "#term" => "The name for the currency argument in commands usage message",
            "term" => $this->currencyTerm,

            "#default-currency" => <<<'EOT'
                The default currency to use in commands if the user does not specify one.
                If this is set to ~, the user is required to specify the currency for every command.
                This option can be overridden in the config for individual commands.
                EOT,
            "default-currency" => $this->defaultCurrency,
        ];
    }

    public function cloneWithConfig(array $config) : self {
        $self = clone $this;

        foreach($config as $key => $value) {
            match($key) {
                "default-currency" => $self->defaultCurrency = $value,
                "allowed-currencies" => $self->allowedCurrencies = $this->validateCurrencies($value),
                default => throw new ConfigException("Unknown config key $key"),
            };
        }

        return $self;
    }

    /**
     * @param list<string> $currencies
     * @return list<string>
     */
    private function validateCurrencies(array $currencies) : array {
        foreach($currencies as $currency) {
            if(!in_array($currency, $this->currencies, true)) {
                throw new ConfigException("Unknown currency $currency");
            }
        }

        return $currencies;
    }

    /**
     * @return SchemaVariable<CurrencyVars, string>
     */
    private function createCurrencyVariable() : SchemaVariable {
        // @phpstan-ignore-next-line
        return new SchemaVariable(
            type: SchemaVariable::TYPE_STRING,
            name: $this->currencyTerm,
            // @phpstan-ignore-next-line
            populate: fn($v, $currency) => $v->currency = $currency,
            enumValues: $this->allowedCurrencies ?? $this->currencies,
        );
    }

    public function getRequiredVariables() : iterable {
        if($this->defaultCurrency === null) {
            yield $this->createCurrencyVariable();
        }
    }

    public function getOptionalVariables() : iterable {
        if($this->defaultCurrency !== null) {
            yield $this->createCurrencyVariable();
        }
    }

    public function newV() : CurrencyVars {
        $v = new CurrencyVars;

        if($this->defaultCurrency !== null) {
            $v->currency = $this->defaultCurrency;
        }

        return $v;
    }

    public function vToLabels($v, string $playerPath) : array {
        return [
            AccountLabels::PLAYER_UUID => "{{$playerPath} uuid}",
            self::LABEL_CURRENCY => $v->currency,
        ];
    }
}
