<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionRenewed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $subscriptionCode,
        public readonly string $provider,
        public readonly string $invoiceReference,
        public readonly array $data
    ) {}
}
