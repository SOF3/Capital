<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Closure;
use Logger;
use PrefixedLogger;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\MainClass;

use function get_class;
use function is_subclass_of;

/**
 * A class that stores all singleton values.
 *
 * AwaitStd is a singleton value, so it is stored in this class.
 */
final class Context implements Singleton {
    /** @var array<class-string<Singleton|AwaitStd>, Singleton|AwaitStd> */
    private array $storage = [];

    public function __construct(private Logger $logger) {
        $this->store($this);
    }

    /**
     * Stores a singleton value.
     */
    public function store(Singleton|AwaitStd $object) : void {
        $this->storage[get_class($object)] = $object;

        if($object instanceof Singleton) {
            $event = new StoreEvent($this, $object);
            $event->call();

            $this->logger->debug("Initialized " . get_class($object));
        }
    }

    /**
     * @template T of Singleton|AwaitStd
     * @param class-string<T> $class
     * @return T|null
     */
    public function fetchClass(string $class) : Singleton|AwaitStd|null {
        if(isset($this->storage[$class])) {
            /** @var T $object */
            $object = $this->storage[$class];
            return $object;
        }

        return null;
    }

    public static function get(Context $context) : static {
        return $context;
    }

    /**
     * Calls a function where parameters are resolved as singletons from the context.
     * Returns the result of the function.
     */
    //@phpstan-ignore-next-line
    public function call(callable $fn) {
        $reflect = new ReflectionFunction(Closure::fromCallable($fn));
        $args = $this->resolveArgs($reflect, null);
        $fn(...$args);
    }

    /**
     * @return list<mixed>
     */
    public function resolveArgs(ReflectionFunctionAbstract $fn, ?string $loggerPrefix) : array {
        $args = [];

        $fnName = $fn->getName();
        if($fn instanceof ReflectionMethod) {
            $fnName = $fn->getDeclaringClass()->getName() . "::" . $fnName;
        }

        foreach($fn->getParameters() as $param) {
            $paramType = $param->getType();
            if(!($paramType instanceof ReflectionNamedType)) {
                throw new RuntimeException("$fnName parameter $paramType is not a named type");
            }

            $paramClass = $paramType->getName();
            if(!is_subclass_of($paramClass, Singleton::class) && $paramClass !== AwaitStd::class && $paramClass !== Logger::class) {
                throw new RuntimeException("$fnName parameter $paramClass is not a singleton");
            }

            if($paramClass === AwaitStd::class) {
                $args[] = $this->fetchClass(AwaitStd::class);
            } elseif($paramClass === Logger::class) {
                // TODO generalize this with factory pattern

                /** @var MainClass $main */
                $main = $this->storage[MainClass::class];

                $logger = $main->getLogger();
                if($loggerPrefix !== null) {
                    $logger = new PrefixedLogger($logger, $loggerPrefix);
                }

                $args[] = $logger;
            } else {
                /** @var class-string<Singleton> $paramClass */
                $args[] = $paramClass::get($this);
            }
        }

        return $args;
    }
}
