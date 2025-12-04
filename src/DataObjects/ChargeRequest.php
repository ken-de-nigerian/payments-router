<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\DataObjects;

use InvalidArgumentException;

/**
 * Class ChargeRequest
 *
 * Data transfer object for payment charge requests
 */
readonly class ChargeRequest
{
    public function __construct(
        public float $amount,
        public string $currency,
        public string $email,
        public ?string $reference = null,
        public ?string $callbackUrl = null,
        public array $metadata = [],
        public ?string $description = null,
        public ?array $customer = null,
        public ?array $customFields = null,
        public ?array $split = null,
    ) {
        $this->validate();
    }

    /**
     * Validate the request data
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }

        if (empty($this->currency)) {
            throw new InvalidArgumentException('Currency is required');
        }

        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }

        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter code');
        }
    }

    /**
     * Convert amount to minor units (cents, kobo, etc.)
     */
    public function getAmountInMinorUnits(): int
    {
        return (int) round($this->amount * 100);
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): static
    {
        return new static(
            amount: (float) ($data['amount'] ?? 0),
            currency: strtoupper($data['currency'] ?? ''),
            email: $data['email'] ?? '',
            reference: $data['reference'] ?? null,
            callbackUrl: $data['callback_url'] ?? null,
            metadata: $data['metadata'] ?? [],
            description: $data['description'] ?? null,
            customer: $data['customer'] ?? null,
            customFields: $data['custom_fields'] ?? null,
            split: $data['split'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'email' => $this->email,
            'reference' => $this->reference,
            'callback_url' => $this->callbackUrl,
            'metadata' => $this->metadata,
            'description' => $this->description,
            'customer' => $this->customer,
            'custom_fields' => $this->customFields,
            'split' => $this->split,
        ];
    }
}
