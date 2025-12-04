<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\DataObjects;

use InvalidArgumentException;

/**
 * Class ChargeRequest
 *
 * Data transfer object for payment charge requests
 */
class ChargeRequest
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $email,
        public readonly ?string $reference = null,
        public readonly ?string $callbackUrl = null,
        public readonly array $metadata = [],
        public readonly ?string $description = null,
        public readonly ?array $customer = null,
        public readonly ?array $customFields = null,
        public readonly ?array $split = null,
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

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }

        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter code');
        }
    }

    /**
     * Convert amount to minor units (cents, kobo, etc.)
     *
     * @return int
     */
    public function getAmountInMinorUnits(): int
    {
        return (int) round($this->amount * 100);
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
     *
     * @return array
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
