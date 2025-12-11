<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;

/**
 * Payment verification failed event.
 *
 * Dispatched after a successful verify() operation that results in a failed state.
 * This provides a clean hook for the host application to run business logic
 * (e.g., sending failure notifications, updating order status, handling refunds)
 * immediately after failed payment verification.
 */
class PaymentVerificationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $reference,
        public readonly VerificationResponseDTO $verification,
        public readonly string $provider
    ) {}
}
