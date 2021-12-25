<?php

declare(strict_types=1);

namespace SOFe\Capital\TypeMap;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use SOFe\AwaitStd\AwaitStd;

use function get_class;

final class TypeMap {
    /** @var array<class-string, object> */
    private array $objects = [];

    public function store(object $object) : void {
        $this->objects[get_class($object)] = $object;
    }

    /**
     * @template T of Singleton|AwaitStd
     * @param class-string<T> $class
     * @return T
     */
    public function get(string $class) : object {
        if(!isset($this->objects[$class])) {
            $this->objects[$class] = $this->instantiate($class);
        }

        /** @var T $object */
        $object = $this->objects[$class];
        return $object;
    }

    /**
     * @template T of SingletonArgs|AwaitStd
     * @param class-string<T> $class
     * @return T
     */
    public function instantiate(string $class) : object {
        $reflect = new ReflectionClass($class);

        if(!$reflect->implementsInterface(Singleton::class) && !$reflect->implementsInterface(SingletonArgs::class)) {
            throw new RuntimeException("$class does not implement Singleton or SingletonArgs");
        }

        $constructor = $reflect->getConstructor();
        if($constructor === null) {
            return $reflect->newInstance();
        }

        $params = [];
        foreach($constructor->getParameters() as $param) {
            $paramType = $param->getType();
            if(!($paramType instanceof ReflectionNamedType)) {
                throw new RuntimeException("Constructor parameter $paramType is not a named type");
            }

            $params[] = $this->get($paramType->getName());
        }

        $object = $reflect->newInstanceArgs($params);
        return $object;
    }
}
