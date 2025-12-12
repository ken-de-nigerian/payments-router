<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\DataObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ChargeRequestDTO - Payment Request Data Object
 */
final readonly class ChargeRequestDTO
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
        public ?array $channels = null,
        public ?string $idempotencyKey = null,
    ) {
        $this->validate();
    }

    /**
     * Check that all required payment data is valid.
     * Makes sure amount is positive, email is valid, currency is three letters, etc.
     *
     * @throws InvalidArgumentException If any data is invalid.
     */
    private function validate(): void
    {
        if ($this->amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }

        if ($this->amount > 999999999.99) {
            throw new InvalidArgumentException('Amount exceeds maximum allowed value');
        }

        if (empty($this->currency)) {
            throw new InvalidArgumentException('Currency is required');
        }

        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code');
        }

        if (! ctype_alpha($this->currency)) {
            throw new InvalidArgumentException('Currency must contain only letters');
        }

        if (! $this->isValidEmail($this->email)) {
            throw new InvalidArgumentException('Invalid email address');
        }

        if ($this->callbackUrl !== null && ! $this->isValidUrl($this->callbackUrl)) {
            throw new InvalidArgumentException('Invalid callback URL');
        }

        if ($this->reference !== null && ! $this->isValidReference($this->reference)) {
            throw new InvalidArgumentException('Invalid reference format');
        }
    }

    /**
     * Enhanced email validation (RFC 5322 compliant)
     */
    private function isValidEmail(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        [$local, $domain] = explode('@', $email);

        if (strlen($local) > 64) {
            return false;
        }

        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            return false;
        }

        $suspiciousPatterns = [
            '/\.\./',
            '/@\./',
            '/\.$/',
            '/^\./',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate URL format
     */
    private function isValidUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (app()->environment('production')) {
            if (! str_starts_with($url, 'https://')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate reference format (alphanumeric, underscore, hyphen)
     */
    private function isValidReference(string $reference): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{1,255}$/', $reference) === 1;
    }

    /**
     * Convert the amount to the smallest currency unit (cents, kobo, etc.).
     *
     * Payment providers need amounts in the smallest unit:
     * - $100.00 becomes 10,000 cents
     * - ₦100.00 becomes 10,000 kobos
     *
     * This method rounds to avoid floating-point precision issues.
     *
     * @return int Amount in minor units (always an integer)
     */
    public function getAmountInMinorUnits(): int
    {
        return (int) round($this->amount * 100);
    }

    /**
     * Create from array
     *
     * Automatically generates an idempotency key if one is not provided
     * to ensure consistent key formatting across all providers.
     */
    public static function fromArray(array $data): ChargeRequestDTO
    {
        $amount = isset($data['amount']) ? round((float) $data['amount'], 2) : 0.0;

        $idempotencyKey = $data['idempotency_key'] ?? self::generateIdempotencyKey();

        return new self(
            amount: $amount,
            currency: strtoupper($data['currency'] ?? ''),
            email: $data['email'] ?? '',
            reference: $data['reference'] ?? null,
            callbackUrl: $data['callback_url'] ?? null,
            metadata: $data['metadata'] ?? [],
            description: $data['description'] ?? null,
            customer: $data['customer'] ?? null,
            customFields: $data['custom_fields'] ?? null,
            split: $data['split'] ?? null,
            channels: $data['channels'] ?? null,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Generate a unique idempotency key.
     *
     * Uses UUID v4 format for maximum uniqueness and compatibility
     * across all payment providers.
     */
    protected static function generateIdempotencyKey(): string
    {
        return Str::uuid()->toString();
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
            'channels' => $this->channels,
        ];
    }
}
