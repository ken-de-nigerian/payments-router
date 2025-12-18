<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

interface ChannelMapperInterface
{
    /**
     * @param  array<int, string>|null  $channels
     * @return array<int, string>|null
     */
    public function mapChannels(?array $channels, string $provider): ?array;

    /**
     * @param  array<int, string>|null  $channels
     */
    public function shouldIncludeChannels(string $provider, ?array $channels): bool;

    public function supportsChannels(string $provider): bool;
}
