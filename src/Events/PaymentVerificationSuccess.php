<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;

final class PaymentVerificationSuccess
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $reference,
        public readonly VerificationResponseDTO $verification,
        public readonly string $provider
    ) {}
}
