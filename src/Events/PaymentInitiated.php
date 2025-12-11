<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;

/**
 * Payment initiated event.
 *
 * Dispatched after a successful charge() operation, before returning ChargeResponseDTO.
 * This provides a clean hook for the host application to run business logic
 * (e.g., sending email confirmations, updating inventory, notifying internal systems)
 * immediately after payment initialization.
 */
class PaymentInitiated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ChargeRequestDTO $request,
        public readonly ChargeResponseDTO $response,
        public readonly string $provider
    ) {}
}
