<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

interface StatusNormalizerInterface
{
    public function normalize(string $status, ?string $provider = null): string;

    /**
     * @param  array<string, array<int, string>>  $mappings
     */
    public function registerProviderMappings(string $provider, array $mappings): self;

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    public function getProviderMappings(): array;

    /**
     * @return array<string, array<int, string>>
     */
    public function getDefaultMappings(): array;
}
