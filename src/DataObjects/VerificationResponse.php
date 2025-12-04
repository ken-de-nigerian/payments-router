<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\DataObjects;

/**
 * Class VerificationResponse
 *
 * Data transfer object for payment verification responses
 */
class VerificationResponse
{
    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $paidAt = null,
        public readonly array $metadata = [],
        public readonly ?string $provider = null,
        public readonly ?string $channel = null,
        public readonly ?string $cardType = null,
        public readonly ?string $bank = null,
        public readonly ?array $customer = null,
    ) {
    }

    /**
     * Create from array
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            reference: $data['reference'] ?? '',
            status: $data['status'] ?? 'unknown',
            amount: (float) ($data['amount'] ?? 0),
            currency: strtoupper($data['currency'] ?? ''),
            paidAt: $data['paid_at'] ?? null,
            metadata: $data['metadata'] ?? [],
            provider: $data['provider'] ?? null,
            channel: $data['channel'] ?? null,
            cardType: $data['card_type'] ?? null,
            bank: $data['bank'] ?? null,
            customer: $data['customer'] ?? null,
        );
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paid_at' => $this->paidAt,
            'metadata' => $this->metadata,
            'provider' => $this->provider,
            'channel' => $this->channel,
            'card_type' => $this->cardType,
            'bank' => $this->bank,
            'customer' => $this->customer,
        ];
    }

    /**
     * Check if payment was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return in_array(strtolower($this->status), ['success', 'succeeded', 'completed', 'successful']);
    }

    /**
     * Check if payment failed
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return in_array(strtolower($this->status), ['failed', 'cancelled', 'declined']);
    }

    /**
     * Check if payment is pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return strtolower($this->status) === 'pending';
    }
}
