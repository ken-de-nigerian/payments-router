<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;

/**
 * Verification response resource.
 */
class VerificationResource extends JsonResource
{
    /**
     * Transform resource to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var VerificationResponseDTO $this */
        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'provider' => $this->provider ?? null,
            'channel' => $this->channel,
            'amount' => [
                'value' => $this->amount,
                'currency' => $this->currency,
            ],
            'paid_at' => $this->paidAt ? (is_string($this->paidAt) ? $this->paidAt : $this->paidAt->toIso8601String()) : null,
            'metadata' => $this->metadata,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
