<?php

namespace App\Support;

/**
 * Converts between the integer minor-unit amounts stored in the database
 * (e.g. paisa) and the decimal values used at the API/UI boundary. This is
 * the only place that conversion happens — controllers and resources call
 * through it rather than doing ad-hoc `* 100` / `/ 100` arithmetic.
 */
final class Money
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currencyCode = 'BDT',
        private readonly int $decimalPlaces = 2,
    ) {}

    public static function fromDecimal(float|string|null $decimal, string $currencyCode = 'BDT', int $decimalPlaces = 2): ?self
    {
        if ($decimal === null || $decimal === '') {
            return null;
        }

        return new self((int) round(((float) $decimal) * (10 ** $decimalPlaces)), $currencyCode, $decimalPlaces);
    }

    public function toDecimal(): float
    {
        return round($this->amountMinor / (10 ** $this->decimalPlaces), $this->decimalPlaces);
    }
}
