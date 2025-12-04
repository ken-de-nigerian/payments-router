<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\Contracts;

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
     * @param float $amount Amount to convert
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @return float Converted amount
     * @throws \KenDeNigerian\PaymentsRouter\Exceptions\CurrencyException
     */
    public function convert(float $amount, string $from, string $to): float;

    /**
     * Get exchange rate between two currencies
     *
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @return float Exchange rate
     * @throws \KenDeNigerian\PaymentsRouter\Exceptions\CurrencyException
     */
    public function getRate(string $from, string $to): float;

    /**
     * Check if a currency is supported
     *
     * @param string $currency Currency code
     * @return bool
     */
    public function supports(string $currency): bool;
}
