<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

/**
 * Provider detector interface.
 */
interface ProviderDetectorInterface
{
    /**
     * Detect provider from reference.
     */
    public function detectFromReference(string $reference): ?string;

    /**
     * Register provider prefix.
     */
    public function registerPrefix(string $prefix, string $provider): self;

    /**
     * Get registered prefixes.
     *
     * @return array<string, string>
     */
    public function getPrefixes(): array;
}
