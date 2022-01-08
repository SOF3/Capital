<?php

declare(strict_types=1);

namespace SOFe\Capital\Config\Schema;

use InvalidArgumentException;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Schema;
use SOFe\Capital\SchemaVariable;

/**
 * A schema where each player has one account for each currency.
 *
 * @implements Schema<CurrencySchemaVariables>
 */
final class CurrencySchema implements Schema {
    public const LABEL_CURRENCY = "capital/currencySchema/currency";

    private ?string $defaultCurrency = null;

    /**
     * @param list<string> $currencies
     */
    public function __construct(
        private array $currencies,
        private string $currencyTerm = "Currency",
    ) {}

    public function cloneWithConfig(array $config) : self {
        $self = clone $this;

        foreach($config as $key => $value) {
            match($key) {
                "default-currency" => $self->defaultCurrency = $value,
                default => throw new InvalidArgumentException("Unknown config key $key"),
            };
        }

        return $self;
    }

    /**
     * @return SchemaVariable<CurrencySchemaVariables, string>
     */
    private function createCurrencyVariable() : SchemaVariable {
        // @phpstan-ignore-next-line
        return new SchemaVariable(
            type: SchemaVariable::TYPE_STRING,
            name: $this->currencyTerm,
            // @phpstan-ignore-next-line
            populate: fn($v, $currency) => $v->currency = $currency,
            enumValues: $this->currencies,
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

    public function newV() : CurrencySchemaVariables {
        $v = new CurrencySchemaVariables;

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
