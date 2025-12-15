<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;

final class PaymentInitiated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ChargeRequestDTO $request,
        public readonly ChargeResponseDTO $response,
        public readonly string $provider
    ) {}
}
