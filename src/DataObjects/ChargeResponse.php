<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\DataObjects;

/**
 * Class ChargeResponse
 *
 * Data transfer object for payment charge responses
 */
class ChargeResponse
{
    public function __construct(
        public readonly string $reference,
        public readonly string $authorizationUrl,
        public readonly string $accessCode,
        public readonly string $status,
        public readonly array $metadata = [],
        public readonly ?string $provider = null,
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
            authorizationUrl: $data['authorization_url'] ?? '',
            accessCode: $data['access_code'] ?? '',
            status: $data['status'] ?? 'pending',
            metadata: $data['metadata'] ?? [],
            provider: $data['provider'] ?? null,
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
            'authorization_url' => $this->authorizationUrl,
            'access_code' => $this->accessCode,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'provider' => $this->provider,
        ];
    }

    /**
     * Check if charge was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return in_array(strtolower($this->status), ['success', 'succeeded', 'completed']);
    }

    /**
     * Check if charge is pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return strtolower($this->status) === 'pending';
    }
}
