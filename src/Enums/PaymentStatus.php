<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Enums;

use ValueError;

/**
 * Payment status enum.
 */
enum PaymentStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';

    /**
     * Check if status is successful.
     */
    public function isSuccessful(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * Check if status is failed.
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED || $this === self::CANCELLED;
    }

    /**
     * Check if status is pending.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Try to create enum from string.
     */
    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }

    /**
     * Create enum from string.
     *
     * @throws ValueError
     */
    public static function fromString(string $value): self
    {
        return self::from(strtolower(trim($value)));
    }

    /**
     * Check if value is valid status.
     */
    public static function isValid(string $value): bool
    {
        return self::tryFromString($value) !== null;
    }

    /**
     * Check if string status is successful.
     */
    public static function isSuccessfulString(string $status): bool
    {
        $enum = self::tryFromString($status);

        return $enum?->isSuccessful() ?? false;
    }

    /**
     * Check if string status is failed.
     */
    public static function isFailedString(string $status): bool
    {
        $enum = self::tryFromString($status);

        return $enum?->isFailed() ?? false;
    }

    /**
     * Check if string status is pending.
     */
    public static function isPendingString(string $status): bool
    {
        $enum = self::tryFromString($status);

        return $enum?->isPending() ?? false;
    }
}
