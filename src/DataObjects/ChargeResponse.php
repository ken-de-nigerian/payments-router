<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\DataObjects;

/**
 * Class ChargeResponse
 *
 * Data transfer object for payment charge responses
 */
readonly class ChargeResponse
{
    public function __construct(
        public string $reference,
        public string $authorizationUrl,
        public string $accessCode,
        public string $status,
        public array $metadata = [],
        public ?string $provider = null,
    ) {}

    /**
     * Create from array
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
     */
    public function isSuccessful(): bool
    {
        return in_array(strtolower($this->status), ['success', 'succeeded', 'completed']);
    }

    /**
     * Check if charge is pending
     */
    public function isPending(): bool
    {
        return strtolower($this->status) === 'pending';
    }
}
