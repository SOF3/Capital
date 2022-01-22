<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Closure;
use Generator;
use Logger;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital as C;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use function count;
use function file_exists;
use function file_put_contents;
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
        C\Player\Config::class => true,
        C\Analytics\Config::class => true,
        C\Transfer\Config::class => true,
    ];

    /** @var array<class-string, true> */
    private array $semaphore = self::ALL_CONFIGS;
    /** @var list<Closure(bool): void> */
    private array $onSemaphoreEmpty = [];

    public Parser $parser;

    /**
     * @param null|array<string, mixed> $mainConfig data from config.yml
     * @param array<string, mixed> $dbConfig data from db.yml
     */
    public function __construct(
        private Logger $logger,
        private string $dataFolder,
        ?array $mainConfig,
        public array $dbConfig,
    ) {
        if($mainConfig === null) {
            // need to generate new config
            $this->parser = self::createFailSafeParser([]);
        } else {
            $this->parser = new Parser(new ArrayRef($mainConfig), [], false);
        }
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @param Closure(Parser): Generator<mixed, mixed, mixed, T> $loader
     * @return Generator<mixed, mixed, mixed, T>
     */
    public function loadConfig(string $class, Closure $loader) : Generator {
        $parser = $this->parser;

        try {
            $ret = yield from $loader($parser);

            unset($this->semaphore[$class]);
            if(count($this->semaphore) === 0) {
                $closures = $this->onSemaphoreEmpty;
                $this->onSemaphoreEmpty = [];
                foreach($closures as $closure) {
                    $closure(true);
                }
            } else {
                $this->onSemaphoreEmpty[] = yield Await::RESOLVE;
                $ok = yield Await::ONCE;
                if($ok) {
                    return;
                }
            }
        } catch(ConfigException $e) {
            // Check if $this->parser changaed, i.e. another config loader threw an exception.
            if($this->parser === $parser) {
                $this->logger->error("Error loading config.yml: " . $e->getMessage());

                $backupPath = $this->dataFolder . "config.yml.old";
                $i = 1;
                while(file_exists($backupPath)) {
                    $i += 1;
                    $backupPath = $this->dataFolder . "config.yml.old.$i";
                }

                $this->logger->notice("Regenerating new config file. The old file is saved to $backupPath.");

                $this->parser = self::createFailSafeParser($this->parser->getFullConfig());

                $this->semaphore = self::ALL_CONFIGS;
                $closures = $this->onSemaphoreEmpty;
                $this->onSemaphoreEmpty = [];
                foreach($closures as $closure) {
                    $closure(false);
                }
            }
        }

        yield from $loader($this->parser);

        unset($this->semaphore[$class]);
        if(count($this->semaphore) === 0) {
            $closures = $this->onSemaphoreEmpty;
            $this->onSemaphoreEmpty = [];
            foreach($closures as $closure) {
                $closure(true);
            }

            file_put_contents($this->dataFolder . "config.yml", yaml_emit($this->parser->getFullConfig()));
        } else {
            $this->onSemaphoreEmpty[] = yield Await::RESOLVE;
            $ok = yield Await::ONCE;
        }
    }

    /**
     * @return VoidPromise
     */
    public function loadAll(Context $context) : Generator {
        /** @var list<Generator<mixed, mixed, mixed, object>> $gens */
        $gens = [];

        foreach(self::ALL_CONFIGS as $class => $_) {
            $gens[] = $class::get($context);
        }

        yield from Await::all($gens);
    }

    public static function fromSingletonArgs(MainClass $main, Logger $logger) : self {
        if(file_exists($main->getDataFolder() . "config.yml")) {
            $mainConfig = yaml_parse_file($main->getDataFolder() . "config.yml");
        } else {
            $mainConfig = null;
        }

        $main->saveResource("db.yml");
        $dbConfig = yaml_parse_file($main->getDataFolder() . "db.yml");

        return new self($logger, $main->getDataFolder(), $mainConfig, $dbConfig);
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
