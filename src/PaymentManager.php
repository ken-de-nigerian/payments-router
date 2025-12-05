<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr;

use Illuminate\Support\Facades\Config;
use KenDeNigerian\PayZephyr\Contracts\DriverInterface;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequest;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponse;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponse;
use KenDeNigerian\PayZephyr\Drivers\FlutterwaveDriver;
use KenDeNigerian\PayZephyr\Drivers\MonnifyDriver;
use KenDeNigerian\PayZephyr\Drivers\PayPalDriver;
use KenDeNigerian\PayZephyr\Drivers\PaystackDriver;
use KenDeNigerian\PayZephyr\Drivers\StripeDriver;
use KenDeNigerian\PayZephyr\Exceptions\DriverNotFoundException;
use KenDeNigerian\PayZephyr\Exceptions\ProviderException;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;
use Throwable;

/**
 * The core orchestrator for the PayZephyr package.
 *
 * This class is responsible for resolving driver instances, managing the
 * "smart routing" (fallback) logic during charges, and logging transaction
 * lifecycles to the database.
 */
class PaymentManager
{
    /**
     * Cache for instantiated driver objects.
     */
    protected array $drivers = [];

    /**
     * The raw configuration array.
     */
    protected array $config;

    public function __construct()
    {
        $this->config = Config::get('payments', []);
    }

    /**
     * Resolve and return a driver instance.
     *
     * If a name is not provided, the default driver from config is used.
     * Instances are cached in memory to prevent multiple instantiation
     * during the same request lifecycle.
     *
     * @throws DriverNotFoundException If the driver is disabled or the class doesn't exist.
     */
    public function driver(?string $name = null): DriverInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = $this->config['providers'][$name] ?? null;

        if (! $config || ! ($config['enabled'] ?? true)) {
            throw new DriverNotFoundException("Payment driver [$name] not found or disabled");
        }

        $driverClass = $this->resolveDriverClass($config['driver']);

        if (! class_exists($driverClass)) {
            throw new DriverNotFoundException("Driver class [$driverClass] not found");
        }

        $this->drivers[$name] = new $driverClass($config);

        return $this->drivers[$name];
    }

    /**
     * Execute a charge attempt, iterating through a list of providers if necessary.
     *
     * This method implements the Failover/Redundancy pattern:
     * 1. Checks if the provider is "healthy" (API is reachable).
     * 2. Checks if the provider supports the requested currency.
     * 3. Attempts the charge.
     * 4. If successful, logs to DB and returns.
     * 5. If failed, catches the exception and moves to the next provider in the chain.
     *
     * @throws ProviderException If all providers in the chain fail.
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
                    if (! $driver->getCachedHealthCheck()) {
                        logger()->warning("Provider [$providerName] failed health check, skipping");

                        continue;
                    }
                }

                // Check currency support
                if (! $driver->isCurrencySupported($request->currency)) {
                    logger()->info("Provider [$providerName] does not support currency $request->currency");

                    continue;
                }

                $response = $driver->charge($request);

                // Log transaction to database
                $this->logTransaction($request, $response, $providerName);

                logger()->info("Payment charged successfully via [$providerName]", [
                    'reference' => $response->reference,
                ]);

                return $response;
            } catch (Throwable $e) {
                $exceptions[$providerName] = $e;
                logger()->error("Provider [$providerName] failed", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        throw ProviderException::withContext(
            'All payment providers failed',
            ['exceptions' => array_map(fn ($e) => $e->getMessage(), $exceptions)]
        );
    }

    /**
     * Persist the initial transaction details to the database.
     *
     * Wrapped in a try-catch block to ensure that a logging failure
     * (e.g., DB connection issue) does not cause the actual payment
     * flow to throw an error to the user.
     */
    protected function logTransaction(ChargeRequest $request, ChargeResponse $response, string $provider): void
    {
        if (! config('payments.logging.enabled', true)) {
            return;
        }

        try {
            PaymentTransaction::create([
                'reference' => $response->reference,
                'provider' => $provider,
                'status' => $response->status,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'email' => $request->email,
                'channel' => null, // Will be updated by webhook
                'metadata' => $request->metadata,
                'customer' => $request->customer,
                'paid_at' => null, // Will be updated on verification/webhook
            ]);
        } catch (Throwable $e) {
            // Don't fail the payment if logging fails
            logger()->error('Failed to log transaction', [
                'error' => $e->getMessage(),
                'reference' => $response->reference,
            ]);
        }
    }

    /**
     * Verify a transaction reference.
     *
     * If a specific provider is not given, this method attempts to find the
     * transaction across ALL configured providers.
     * This is useful when the frontend doesn't know which provider successfully processed the fallback charge.
     *
     * @throws ProviderException If the reference cannot be found on any provider.
     */
    public function verify(string $reference, ?string $provider = null): VerificationResponse
    {
        $providers = $provider ? [$provider] : array_keys($this->config['providers'] ?? []);
        $exceptions = [];

        foreach ($providers as $providerName) {
            try {
                $driver = $this->driver($providerName);
                $response = $driver->verify($reference);

                // Update transaction in database
                $this->updateTransactionFromVerification($reference, $response);

                return $response;
            } catch (Throwable $e) {
                $exceptions[$providerName] = $e;
            }
        }

        throw ProviderException::withContext(
            "Unable to verify payment reference: $reference",
            ['exceptions' => array_map(fn ($e) => $e->getMessage(), $exceptions)]
        );
    }

    /**
     * Update the local database record based on the verification response.
     *
     * This syncs the status, channel, and payment timestamp.
     */
    protected function updateTransactionFromVerification(string $reference, VerificationResponse $response): void
    {
        if (! config('payments.logging.enabled', true)) {
            return;
        }

        try {
            PaymentTransaction::where('reference', $reference)->update([
                'status' => $response->status,
                'channel' => $response->channel,
                'paid_at' => $response->isSuccessful() ? ($response->paidAt ?? now()) : null,
            ]);
        } catch (Throwable $e) {
            logger()->error('Failed to update transaction from verification', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);
        }
    }

    /**
     * Retrieve the default driver name from configuration.
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? array_key_first($this->config['providers'] ?? []);
    }

    /**
     * Construct the priority list of providers.
     *
     * Returns an array starting with the default driver, followed by
     * any fallback drivers defined in the config.
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
     * Map a driver alias (e.g., 'paystack') to its fully qualified class name.
     *
     * This allows the config file to be cleaner, using short names instead of
     * full namespaces.
     */
    protected function resolveDriverClass(string $driver): string
    {
        $map = [
            'paystack' => PaystackDriver::class,
            'flutterwave' => FlutterwaveDriver::class,
            'monnify' => MonnifyDriver::class,
            'stripe' => StripeDriver::class,
            'paypal' => PayPalDriver::class,
        ];

        return $map[$driver] ?? $driver;
    }

    /**
     * Return a list of all providers that are enabled in the config.
     */
    public function getEnabledProviders(): array
    {
        return array_filter(
            $this->config['providers'] ?? [],
            fn ($config) => $config['enabled'] ?? true
        );
    }
}
