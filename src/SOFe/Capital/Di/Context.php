<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use Closure;
use Generator;
use Logger;
use PrefixedLogger;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use SOFe\AwaitStd\AwaitStd;
use SOFe\Capital\Plugin\MainClass;

use function array_reverse;
use function get_class;
use function is_subclass_of;
use function microtime;

/**
 * A class that stores all singleton values.
 *
 * AwaitStd is a singleton value, so it is stored in this class.
 */
final class Context implements Singleton {
    /** @var array<class-string<Singleton|AwaitStd>, Singleton|AwaitStd> */
    private array $storage = [];

    private DepGraphWriter $depGraph;
    private float $epoch;

    public function __construct(private Logger $logger) {
        $this->depGraph = new DepGraphWriter;
        $this->epoch = microtime(true);
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
        }

        $this->depGraph->addNode(get_class($object), microtime(true) - $this->epoch);
        $this->logger->debug("Initialized " . get_class($object));
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

    public function addDepEdge(string $from, string $to) : void {
        $this->depGraph->addEdge($from, $to);
    }

    public function getDepGraph() : DepGraphWriter {
        return $this->depGraph;
    }

    public function shutdown() : void {
        foreach(array_reverse($this->storage) as $object) {
            if($object instanceof Singleton) {
                $object->close();
            }
        }
    }

    public static function get(Context $context) : Generator {
        false && yield;
        return $context;
    }

    public static function getOrNull(Context $context) : ?static {
        return $context;
    }

    /**
     * @internal do not use, this is just for implementing interface.
     */
    public function close() : void {}

    /**
     * Calls a function where parameters are resolved as singletons from the context.
     * Returns the result of the function.
     */
    //@phpstan-ignore-next-line
    public function call(callable $fn) : Generator {
        $reflect = new ReflectionFunction(Closure::fromCallable($fn));
        $args = yield from $this->resolveArgs($reflect, null);
        return yield from $fn(...$args);
    }

    /**
     * @return Generator<mixed, mixed, mixed, list<mixed>>
     */
    public function resolveArgs(ReflectionFunctionAbstract $fn, ?string $user) : Generator {
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
                if($user !== null) {
                    $this->addDepEdge($user, AwaitStd::class);
                }
            } elseif($paramClass === Logger::class) {
                // TODO generalize this with factory pattern

                /** @var MainClass $main */
                $main = $this->storage[MainClass::class];

                $logger = $main->getLogger();
                if($user !== null) {
                    $logger = new PrefixedLogger($logger, $user);
                }

                $args[] = $logger;
            } else {
                /** @var class-string<Singleton> $paramClass */
                $args[] = yield from $paramClass::get($this);
                if($user !== null) {
                    $this->addDepEdge($user, $paramClass);
                }
            }
        }

        return $args;
    }
}
