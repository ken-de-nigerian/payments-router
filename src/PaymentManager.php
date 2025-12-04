<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter;

use Illuminate\Support\Facades\Config;
use KenDeNigerian\PaymentsRouter\Contracts\DriverInterface;
use KenDeNigerian\PaymentsRouter\DataObjects\ChargeRequest;
use KenDeNigerian\PaymentsRouter\DataObjects\ChargeResponse;
use KenDeNigerian\PaymentsRouter\DataObjects\VerificationResponse;
use KenDeNigerian\PaymentsRouter\Exceptions\DriverNotFoundException;
use KenDeNigerian\PaymentsRouter\Exceptions\ProviderException;

/**
 * Class PaymentManager
 *
 * Manages payment drivers and handles fallback logic
 */
class PaymentManager
{
    protected array $drivers = [];
    protected array $config;

    public function __construct()
    {
        $this->config = Config::get('payments', []);
    }

    /**
     * Get driver instance
     *
     * @param string|null $name
     * @return DriverInterface
     * @throws DriverNotFoundException
     */
    public function driver(?string $name = null): DriverInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = $this->config['providers'][$name] ?? null;

        if (!$config || !($config['enabled'] ?? true)) {
            throw new DriverNotFoundException("Payment driver [{$name}] not found or disabled");
        }

        $driverClass = $this->resolveDriverClass($config['driver']);

        if (!class_exists($driverClass)) {
            throw new DriverNotFoundException("Driver class [{$driverClass}] not found");
        }

        $this->drivers[$name] = new $driverClass($config);

        return $this->drivers[$name];
    }

    /**
     * Attempt charge across multiple providers with fallback
     *
     * @param ChargeRequest $request
     * @param array|null $providers
     * @return ChargeResponse
     * @throws ProviderException
     */
    public function chargeWithFallback(ChargeRequest $request, ?array $providers = null): ChargeResponse
    {
        $providers = $providers ?? $this->getFallbackChain();
        $exceptions = [];

        foreach ($providers as $providerName) {
            try {
                $driver = $this->driver($providerName);

                // Health check if enabled
                if ($this->config['health_check']['enabled'] ?? true) {
                    if (!$driver->getCachedHealthCheck()) {
                        logger()->warning("Provider [{$providerName}] failed health check, skipping");
                        continue;
                    }
                }

                // Check currency support
                if (!$driver->isCurrencySupported($request->currency)) {
                    logger()->info("Provider [{$providerName}] does not support currency {$request->currency}");
                    continue;
                }

                $response = $driver->charge($request);

                logger()->info("Payment charged successfully via [{$providerName}]", [
                    'reference' => $response->reference,
                ]);

                return $response;
            } catch (\Throwable $e) {
                $exceptions[$providerName] = $e;
                logger()->error("Provider [{$providerName}] failed", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        throw ProviderException::withContext(
            'All payment providers failed',
            ['exceptions' => array_map(fn($e) => $e->getMessage(), $exceptions)]
        );
    }

    /**
     * Verify payment across all providers
     *
     * @param string $reference
     * @param string|null $provider
     * @return VerificationResponse
     * @throws ProviderException
     */
    public function verify(string $reference, ?string $provider = null): VerificationResponse
    {
        $providers = $provider ? [$provider] : array_keys($this->config['providers'] ?? []);
        $exceptions = [];

        foreach ($providers as $providerName) {
            try {
                $driver = $this->driver($providerName);
                return $driver->verify($reference);
            } catch (\Throwable $e) {
                $exceptions[$providerName] = $e;
            }
        }

        throw ProviderException::withContext(
            "Unable to verify payment reference: {$reference}",
            ['exceptions' => array_map(fn($e) => $e->getMessage(), $exceptions)]
        );
    }

    /**
     * Get default driver name
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? array_key_first($this->config['providers'] ?? []);
    }

    /**
     * Get fallback provider chain
     *
     * @return array
     */
    public function getFallbackChain(): array
    {
        $chain = [$this->getDefaultDriver()];

        if ($fallback = $this->config['fallback'] ?? null) {
            $chain[] = $fallback;
        }

        return array_unique(array_filter($chain));
    }

    /**
     * Resolve driver class from config
     *
     * @param string $driver
     * @return string
     */
    protected function resolveDriverClass(string $driver): string
    {
        $map = [
            'paystack' => \KenDeNigerian\PaymentsRouter\Drivers\PaystackDriver::class,
            'flutterwave' => \KenDeNigerian\PaymentsRouter\Drivers\FlutterwaveDriver::class,
            'monnify' => \KenDeNigerian\PaymentsRouter\Drivers\MonnifyDriver::class,
            'stripe' => \KenDeNigerian\PaymentsRouter\Drivers\StripeDriver::class,
            'paypal' => \KenDeNigerian\PaymentsRouter\Drivers\PayPalDriver::class,
        ];

        return $map[$driver] ?? $driver;
    }

    /**
     * Get all enabled providers
     *
     * @return array
     */
    public function getEnabledProviders(): array
    {
        return array_filter(
            $this->config['providers'] ?? [],
            fn($config) => $config['enabled'] ?? true
        );
    }
}
