<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;

/**
 * Charge response resource.
 */
class ChargeResource extends JsonResource
{
    /**
     * Transform resource to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChargeResponseDTO $this */
        return [
            'reference' => $this->reference,
            'authorization_url' => $this->authorizationUrl,
            'status' => $this->status,
            'provider' => $this->provider ?? null,
            'amount' => [
                'value' => $this->metadata['amount'] ?? null,
                'currency' => $this->metadata['currency'] ?? null,
            ],
            'metadata' => $this->metadata,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
