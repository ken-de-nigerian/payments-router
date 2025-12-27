<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $subscriptionCode,
        public readonly string $provider,
        public readonly array $data
    ) {}
}
