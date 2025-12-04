<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

use KenDeNigerian\PayZephyr\Exceptions\CurrencyException;

/**
 * Interface CurrencyConverterInterface
 *
 * Interface for currency conversion implementations
 */
interface CurrencyConverterInterface
{
    /**
     * Convert amount from one currency to another
     *
     * @param  float  $amount  Amount to convert
     * @param  string  $from  Source currency code
     * @param  string  $to  Target currency code
     * @return float Converted amount
     *
     * @throws CurrencyException
     */
    public function convert(float $amount, string $from, string $to): float;

    /**
     * Get exchange rate between two currencies
     *
     * @param  string  $from  Source currency code
     * @param  string  $to  Target currency code
     * @return float Exchange rate
     *
     * @throws CurrencyException
     */
    public function getRate(string $from, string $to): float;

    /**
     * Check if a currency is supported
     *
     * @param  string  $currency  Currency code
     */
    public function supports(string $currency): bool;
}
