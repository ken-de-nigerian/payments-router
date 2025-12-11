<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;

/**
 * Payment verification success event.
 *
 * Dispatched after a successful verify() operation that results in a successful state.
 * This provides a clean hook for the host application to run business logic
 * (e.g., sending confirmation emails, updating order status, fulfilling products)
 * immediately after successful payment verification.
 */
class PaymentVerificationSuccess
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $reference,
        public readonly VerificationResponseDTO $verification,
        public readonly string $provider
    ) {}
}
