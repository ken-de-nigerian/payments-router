<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Services;

use KenDeNigerian\PayZephyr\Contracts\ProviderDetectorInterface;

/**
 * Provider detector service.
 */
final class ProviderDetector implements ProviderDetectorInterface
{
    /** @var array<string, string> */
    protected array $prefixes = [
        'PAYSTACK' => 'paystack',
        'FLW' => 'flutterwave',
        'MON' => 'monnify',
        'STRIPE' => 'stripe',
        'PAYPAL' => 'paypal',
        'SQUARE' => 'square',
    ];

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
