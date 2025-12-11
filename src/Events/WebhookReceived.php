<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Webhook received event.
 */
class WebhookReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
        public readonly ?string $reference = null
    ) {}
}
