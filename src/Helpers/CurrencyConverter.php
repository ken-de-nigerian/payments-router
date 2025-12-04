<?php
namespace Nwaneri\PaymentsRouter\Helpers;

class CurrencyConverter
{
    protected $cache;
    protected $driver;
    public function __construct($cache, $driver = null)
    {
        $this->cache = $cache;
        $this->driver = $driver;
    }

    public function convert(int $amount, string $from, string $to): int
    {
        // Default: return same amount. Implement rates via external API in real use.
        return $amount;
    }
}
