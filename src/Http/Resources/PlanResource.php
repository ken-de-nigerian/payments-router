<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KenDeNigerian\PayZephyr\DataObjects\PlanResponseDTO;

final class PlanResource extends JsonResource
{
    /**
     * Transform resource to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PlanResponseDTO $this */
        return [
            'plan_code' => $this->planCode,
            'name' => $this->name,
            'amount' => [
                'value' => $this->amount,
                'currency' => $this->currency,
            ],
            'interval' => $this->interval,
            'description' => $this->description,
            'invoice_limit' => $this->invoiceLimit,
            'metadata' => $this->metadata,
            'provider' => $this->provider ?? null,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
