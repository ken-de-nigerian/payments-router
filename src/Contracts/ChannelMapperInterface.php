<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

interface ChannelMapperInterface
{
    public function mapChannels(?array $channels, string $provider): ?array;

    public function shouldIncludeChannels(string $provider, ?array $channels): bool;

    public function supportsChannels(string $provider): bool;
}
