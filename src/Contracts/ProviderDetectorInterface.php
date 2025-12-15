<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

interface ProviderDetectorInterface
{
    public function detectFromReference(string $reference): ?string;

    public function registerPrefix(string $prefix, string $provider): self;

    public function getPrefixes(): array;
}
