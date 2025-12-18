<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Services;

use KenDeNigerian\PayZephyr\Contracts\ProviderDetectorInterface;

final class ProviderDetector implements ProviderDetectorInterface
{
    /** @var array<string, string> */
    protected array $prefixes = [];

    public function __construct()
    {
        $this->prefixes = $this->loadPrefixesFromConfig();
    }

    /**
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

    public function registerPrefix(string $prefix, string $provider): self
    {
        $this->prefixes[strtoupper($prefix)] = $provider;

        return $this;
    }

    public function getPrefixes(): array
    {
        return $this->prefixes;
    }
}
