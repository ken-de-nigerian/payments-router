<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

/**
 * Channel mapper interface.
 */
interface ChannelMapperInterface
{
    /**
     * Map channels to provider format.
     *
     * @param  array<string>|null  $channels
     */
    public function mapChannels(?array $channels, string $provider): ?array;

    /**
     * Get default channels for provider.
     *
     * @return array<string>
     */
    public function getDefaultChannels(string $provider): array;

    /**
     * Check if channels should be included.
     *
     * @param  array<string>|null  $channels
     */
    public function shouldIncludeChannels(string $provider, ?array $channels): bool;

    /**
     * Check if provider supports channels.
     */
    public function supportsChannels(string $provider): bool;
}
