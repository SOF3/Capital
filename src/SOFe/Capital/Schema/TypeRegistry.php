<?php

declare(strict_types=1);

namespace SOFe\Capital\Schema;

use RuntimeException;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;

final class TypeRegistry implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    /** @var array<string, class-string<Schema<object>>> */
    private array $types = [];

    /**
     * @template V of object
     * @param class-string<Schema<V>> $class
     */
    public function register(string $name, string $class) : void {
        $this->types[$name] = $class;
    }

    /**
     * @param array<string, mixed> $config
     * @return Schema<object>
     */
    public function build(array $config) : Schema {
        $type = $config["type"];
        if(!isset($this->types[$type])) {
            throw new RuntimeException("Unknown schema type $type");
        }
        return ($this->types[$type])::build($config);
    }
}
