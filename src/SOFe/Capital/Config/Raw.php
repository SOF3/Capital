<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use AssertionError;
use Closure;
use Generator;
use Logger;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital as C;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use function array_shift;
use function count;
use function file_exists;
use function file_put_contents;
use function get_class;
use function gettype;
use function is_array;
use function yaml_emit;
use function yaml_parse_file;

/**
 * Stores the raw config data. Should not be used directly except for config parsing.
 */
final class Raw implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    private const ALL_CONFIGS = [
        C\Database\Config::class => true,
        C\Schema\Config::class => true,
        C\Analytics\Config::class => true,
        C\Transfer\Config::class => true,
    ];

    /** @var null|list<Closure(): void> resolve functions called when all configs are loaded, or null if the configs have not started loading yet */
    private ?array $onConfigLoaded = null;

    /** @var array<class-string<ConfigInterface>, object> Loaded config files are stored here. */
    private array $loadedConfigs = [];

    /** @var array<class-string<ConfigInterface>, ConfigInterface|list<Closure(ConfigInterface): void>> Callbacks for internal awaits between config loaders */
    private array $configInternalAwaits = [];

    public Parser $parser;

    /**
     * @param null|array<string, mixed> $mainConfig data from config.yml
     * @param array<string, mixed> $dbConfig data from db.yml
     */
    public function __construct(
        private Logger $logger,
        private Context $di,
        private string $dataFolder,
        ?array $mainConfig,
        public array $dbConfig,
    ) {
        if ($mainConfig === null) {
            // need to generate new config
            $this->parser = self::createFailSafeParser([]);
        } else {
            $this->parser = new Parser(new ArrayRef($mainConfig), [], false);
        }
    }

    /**
     * @template T of ConfigInterface
     * @param class-string<T> $class
     * @return Generator<mixed, mixed, mixed, T>
     */
    public function loadConfig(string $class) : Generator {
        if (!isset(self::ALL_CONFIGS[$class])) {
            throw new RuntimeException("Config $class not in " . self::class . "::ALL_CONFIGS");
        }

        if ($this->onConfigLoaded === null) {
            // nothing is loaded right now, either finished loading
            if (count($this->loadedConfigs) > 0) {
                return $this->loadedConfigs[$class];
            }

            $this->onConfigLoaded = [];

            yield from $this->loadAll();

            while (count($this->onConfigLoaded) > 0) {
                $resolve = array_shift($this->onConfigLoaded);
                $resolve();
            }

            $this->onConfigLoaded = null;
        } else {
            $this->onConfigLoaded[] = yield Await::RESOLVE;
            yield Await::ONCE;
        }

        $config = $this->loadedConfigs[$class];
        if (!($config instanceof $class)) {
            throw new RuntimeException("$class::parse() returned " . gettype($config));
        }

        return $config;
    }

    /**
     * @param class-string<ConfigInterface> $class
     * @return Generator<mixed, mixed, mixed, void>
     */
    private function loadConfigOnce(string $class) : Generator {
        $instance = yield from $class::parse($this->parser, $this->di, $this);
        $this->loadedConfigs[$class] = $instance;

        $this->logger->debug("Loaded config $class");

        if (isset($this->configInternalAwaits[$class])) {
            $resolves = $this->configInternalAwaits[$class];
            if (!is_array($resolves)) {
                throw new AssertionError("configInternalAwaits should not be the instance when the instance was just created");
            }

            foreach ($resolves as $resolve) {
                $resolve($instance);
            }
        }

        $this->configInternalAwaits[$class] = $instance;
    }

    /**
     * @template T of ConfigInterface
     * @param class-string<T> $class
     * @return Generator<mixed, mixed, mixed, T>
     */
    public function awaitConfigInternal(string $class) : Generator {
        if (!isset($this->configInternalAwaits[$class])) {
            $this->configInternalAwaits[$class] = [];
        } elseif ($this->configInternalAwaits[$class] instanceof ConfigInterface) {
            $instance = $this->configInternalAwaits[$class];
            if (!($instance instanceof $class)) {
                throw new AssertionError("$class::parse() returned " . get_class($instance));
            }
            return $instance; // already loaded
        }

        $this->logger->debug("Internal await for config $class");
        $this->configInternalAwaits[$class][] = yield Await::RESOLVE;

        $instance = yield Await::ONCE;
        $this->logger->debug("Internal await for config $class complete");
        return $instance;
    }

    /**
     * @return VoidPromise
     */
    private function loadAll() : Generator {
        $this->logger->debug("Start loading configs");

        $promises = [];
        /** @var class-string<ConfigInterface> $class */
        foreach (self::ALL_CONFIGS as $class => $_) {
            $promises[$class] = $this->loadConfigOnce($class);
        }

        try {
            $this->configInternalAwaits = [];
            yield from Await::all($promises);
        } catch (ConfigException $e) {
            $this->logger->error("Error loading config.yml: " . $e->getMessage());

            $backupPath = $this->dataFolder . "config.yml.old";
            $i = 1;
            while (file_exists($backupPath)) {
                $i += 1;
                $backupPath = $this->dataFolder . "config.yml.old.$i";
            }

            $this->logger->notice("Regenerating new config file. The old file is saved to $backupPath.");

            $this->parser = self::createFailSafeParser($this->parser->getFullConfig());

            $promises = [];
            /** @var class-string<ConfigInterface> $class */
            foreach (self::ALL_CONFIGS as $class => $_) {
                $promises[$class] = $this->loadConfigOnce($class);
            }

            $this->configInternalAwaits = [];
            yield from Await::all($promises);
        }

        if ($this->parser->isFailSafe()) {
            file_put_contents($this->dataFolder . "config.yml", yaml_emit($this->parser->getFullConfig()));
        }
    }

    public static function fromSingletonArgs(MainClass $main, Context $di, Logger $logger) : self {
        if (file_exists($main->getDataFolder() . "config.yml")) {
            $mainConfig = yaml_parse_file($main->getDataFolder() . "config.yml");
        } else {
            $mainConfig = null;
        }

        $main->saveResource("db.yml");
        $dbConfig = yaml_parse_file($main->getDataFolder() . "db.yml");

        return new self($logger, $di, $main->getDataFolder(), $mainConfig, $dbConfig);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createFailSafeParser(array $data) : Parser {
        $array = new ArrayRef($data);
        $array->set(["#"], <<<'EOT'
            This is the main config file of Capital.
            You can change the values in this file to configure Capital.
            If you change some main settings that change the structure (e.g. schema), Capital will try its best
            to migrate your previous settings to the new structure and overwrite this file.
            The previous file will be stored in config.yml.old.
            EOT);

        return new Parser($array, [], true);
    }
}
