<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use RuntimeException;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Config\ConfigException;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

final class TypeRegistry implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /** @var array<string, class-string<Schema<object>>> */
    private array $types = [];

    public static function fromSingletonArgs(AwaitStd $std) : self {
        $self = new self;

        $self->register("basic", Basic::class);
        $self->register("currency", Currency::class);

        return $self;
    }

    /**
     * @template V of object
     * @param class-string<Schema<V>> $class
     */
    public function register(string $name, string $class) : void {
        $this->types[$name] = $class;
    }

    /**
     * @return array<string, class-string<Schema<object>>>
     */
    public function getTypes() : array {
        return $this->types;
    }

    /**
     * @param array<string, mixed> $config
     * @return Schema<object>
     */
    public function build(array $config) : Schema {
        $type = $config["type"];
        if(!isset($this->types[$type])) {
            throw new ConfigException("Unknown schema type $type");
        }
        return ($this->types[$type])::build($config);
    }

    /**
     * @param array<string, mixed> $config The probably invalid config to infer default schema from.
     * @return Schema<object>
     */
    public function defaultSchema(array $config) : Schema {
        $type = $config["type"];
        if(!isset($this->types[$type])) {
            throw new RuntimeException("Unknown schema type $type");
        }

        return ($this->types[$type])::infer($config);
    }
}
