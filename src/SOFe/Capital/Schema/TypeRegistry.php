<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use SOFe\Capital\Config\Parser;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

final class TypeRegistry implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /** @var array<string, class-string<Schema>> */
    private array $types = [];

    public static function fromSingletonArgs() : self {
        $self = new self;

        $self->register("basic", Basic::class);
        $self->register("currency", Currency::class);

        return $self;
    }

    /**
     * @param class-string<Schema> $class
     */
    public function register(string $name, string $class) : void {
        $this->types[$name] = $class;
    }

    /**
     * @return array<string, class-string<Schema>>
     */
    public function getTypes() : array {
        return $this->types;
    }

    public function build(Parser $config) : Schema {
        $doc = "The type of the schema. Possible values include:\n";
        foreach ($this->types as $typeName => $class) {
            $doc .= "\n$typeName: " . $class::describe();
        }

        $type = $config->expectString("type", "basic", $doc);

        if (!isset($this->types[$type])) {
            $type = $config->failSafe("basic", "Unknown schema type $type");
        }

        return ($this->types[$type])::build($config);
    }
}
