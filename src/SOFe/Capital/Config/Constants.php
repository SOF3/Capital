<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

/**
 * Constants used only for default configuration.
 * These values must not be used directly from any other modules.
 */
final class Constants {
    /** The default distinction of multiple accounts is achieved through the concept of currencies. */
    public const LABEL_CURRENCY = "currency";

    /** The name of the default currency used in default config. */
    public const CURRENCY_NAME = "money";

    /** The info name used to expose the default currency. */
    public const CURRENCY_DEFAULT_INFO = "money";

    /** The label applied on normal payment transactions. */
    public const LABEL_PAYMENT = "payment";

    /** The label applied on operator-related transactions. */
    public const LABEL_OPERATOR = "operator";
}
