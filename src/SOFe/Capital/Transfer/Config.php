<?php

declare(strict_types=1);

namespace SOFe\Capital\Transfer;

use Generator;
use SOFe\Capital\Config\ConfigInterface;
use SOFe\Capital\Config\ConfigTrait;
use SOFe\Capital\Config\Parser;
use SOFe\Capital\Config\Raw;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use function array_filter;
use function count;

final class Config implements Singleton, FromContext, ConfigInterface {
    use SingletonArgs, SingletonTrait, ConfigTrait;

    /**
     * @param list<Method> $transferMethods Methods to initiate money transfer between accounts.
     */
    public function __construct(
        public array $transferMethods,
    ) {}

    public static function parse(Parser $config, Context $di, Raw $raw) : Generator {
        false && yield;

        $transferParser = $config->enter("transfer", <<<'EOT'
            "transfer" tells Capital what methods admins and players can send money through.
            A method can require that one is OP before using it.
            EOT);

        $methodNames = array_filter($transferParser->getKeys(), fn($currency) => $currency[0] !== "#");

        if (count($methodNames) === 0) {
            $transferParser->failSafe(null, "There must be at least one method");
            MethodFactory::writeDefaults($transferParser);
            $methodNames = array_filter($transferParser->getKeys(), fn($currency) => $currency[0] !== "#");
        }

        $methods = [];
        foreach ($methodNames as $method) {
            $methodParser = $transferParser->enter($method, "");
            $methods[] = MethodFactory::build($methodParser, $method);
        }

        return new self($methods);
    }
}
