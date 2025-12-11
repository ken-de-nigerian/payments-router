<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Services;

use KenDeNigerian\PayZephyr\Contracts\ProviderDetectorInterface;

/**
 * Provider detector service.
 *
 * This service dynamically builds its list of known reference prefixes
 * based on the enabled providers defined in the configuration.
 */
final class ProviderDetector implements ProviderDetectorInterface
{
    /** @var array<string, string> */
    protected array $prefixes = [];

    public function __construct()
    {
        $this->prefixes = $this->loadPrefixesFromConfig();
    }

    /**
     * Dynamically loads default prefixes based on all providers in config.
     *
     * Note: We load all providers (not just enabled ones) because we need to
     * detect which provider a reference belongs to, regardless of whether
     * that provider is currently enabled for payments.
     *
     * @return array<string, string>
     */
    protected function loadPrefixesFromConfig(): array
    {
        $config = app('payments.config') ?? config('payments', []);
        $prefixes = [];

        foreach ($config['providers'] ?? [] as $providerName => $providerConfig) {
            $driverName = $providerConfig['driver'] ?? $providerName;
            $prefix = $providerConfig['reference_prefix'] ?? strtoupper($driverName);
            $prefixes[strtoupper($prefix)] = $providerName;
        }

        return $prefixes;
    }

    /**
     * Detect provider from reference.
     */
    public function detectFromReference(string $reference): ?string
    {
        $upperReference = strtoupper($reference);

        foreach ($this->prefixes as $prefix => $provider) {
            if (str_starts_with($upperReference, $prefix.'_')) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Register provider prefix.
     */
    public function registerPrefix(string $prefix, string $provider): self
    {
        $this->prefixes[strtoupper($prefix)] = $provider;

        return $this;
    }

    /**
     * Get registered prefixes.
     *
     * @return array<string, string>
     */
    public function getPrefixes(): array
    {
        return $this->prefixes;
    }
}
