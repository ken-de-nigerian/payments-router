<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

interface StatusNormalizerInterface
{
    public function normalize(string $status, ?string $provider = null): string;

    public function registerProviderMappings(string $provider, array $mappings): self;

    public function getProviderMappings(): array;

    public function getDefaultMappings(): array;
}
