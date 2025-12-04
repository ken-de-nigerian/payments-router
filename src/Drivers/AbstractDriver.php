<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use KenDeNigerian\PaymentsRouter\Contracts\DriverInterface;
use KenDeNigerian\PaymentsRouter\Exceptions\InvalidConfigurationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Abstract Class AbstractDriver
 *
 * Base class for all payment drivers
 */
abstract class AbstractDriver implements DriverInterface
{
    protected Client $client;
    protected array $config;
    protected string $name;

    /**
     * AbstractDriver constructor.
     *
     * @param array $config
     * @throws InvalidConfigurationException
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->validateConfig();
        $this->initializeClient();
    }

    /**
     * Validate driver configuration
     *
     * @throws InvalidConfigurationException
     */
    abstract protected function validateConfig(): void;

    /**
     * Initialize HTTP client
     */
    protected function initializeClient(): void
    {
        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? '',
            'timeout' => $this->config['timeout'] ?? 30,
            'verify' => !($this->config['testing_mode'] ?? false),
            'headers' => $this->getDefaultHeaders(),
        ]);
    }

    /**
     * Get default HTTP headers
     *
     * @return array
     */
    abstract protected function getDefaultHeaders(): array;

    /**
     * Make HTTP request
     *
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function makeRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }

    /**
     * Parse JSON response
     *
     * @param ResponseInterface $response
     * @return array
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function getSupportedCurrencies(): array
    {
        return $this->config['currencies'] ?? [];
    }

    /**
     * Generate unique reference
     *
     * @param string|null $prefix
     * @return string
     */
    protected function generateReference(?string $prefix = null): string
    {
        $prefix = $prefix ?? strtoupper($this->getName());
        return $prefix . '_' . time() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency
     * @return bool
     */
    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }

    /**
     * Get cached health check result
     *
     * @return bool
     */
    public function getCachedHealthCheck(): bool
    {
        $cacheKey = 'payments.health.' . $this->getName();
        $cacheTtl = config('payments.health_check.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () {
            return $this->healthCheck();
        });
    }

    /**
     * Log activity
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (config('payments.logging.enabled', true)) {
            logger()->{$level}("[{$this->getName()}] {$message}", $context);
        }
    }
}
