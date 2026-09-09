<?php

namespace App\Service\Currency;

final class CurrencyFormat
{
    public function __construct(
        public readonly string $code,
        public readonly string $symbol,
        public readonly int $decimalPlaces,
        public readonly string $thousandsSeparator,
        public readonly string $decimalSeparator,
    ) {
    }

    public static function legacyDefault(): self
    {
        return new self('USD', '$', 2, ',', '.');
    }
}
