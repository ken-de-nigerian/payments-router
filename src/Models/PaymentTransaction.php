<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface;
use KenDeNigerian\PayZephyr\Enums\PaymentStatus;
use KenDeNigerian\PayZephyr\Services\StatusNormalizer;
use Throwable;

/**
 * Payment transaction model.
 *
 * @method static where(string $string, string $reference)
 * @method static create(array $array)
 */
final class PaymentTransaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference',
        'provider',
        'status',
        'amount',
        'currency',
        'email',
        'channel',
        'metadata',
        'customer',
        'paid_at',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_transactions';

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        $config = app('payments.config') ?? config('payments', []);
        $tableName = $config['logging']['table'] ?? $this->table;

        return $tableName;
    }

    /**
     * Get attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => AsArrayObject::class,
            'customer' => AsArrayObject::class,
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Set table name.
     */
    public static function setTableName(string $table): void
    {
        $instance = new self;
        $instance->table = $table;
    }

    /**
     * Get database connection name.
     */
    public function getConnectionName(): ?string
    {
        return parent::getConnectionName() ?? (app()->environment('testing') ? 'testing' : null);
    }

    /**
     * Scope: successful payments.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        $successStatuses = [
            PaymentStatus::SUCCESS->value,
            'succeeded',
            'completed',
            'successful',
            'paid',
        ];

        return $query->whereIn('status', $successStatuses);
    }

    /**
     * Scope: failed payments.
     */
    public function scopeFailed(Builder $query): Builder
    {
        $failedStatuses = [
            PaymentStatus::FAILED->value,
            PaymentStatus::CANCELLED->value,
            'declined',
            'rejected',
            'denied',
            'voided',
            'expired',
        ];

        return $query->whereIn('status', $failedStatuses);
    }

    /**
     * Scope: pending payments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::PENDING->value);
    }

    public function isSuccessful(): bool
    {
        try {
            if (function_exists('app')) {
                $normalizer = app(StatusNormalizerInterface::class);
                $normalizedStatus = $normalizer->normalize($this->status);
            } else {
                $normalizedStatus = StatusNormalizer::normalizeStatic($this->status);
            }
        } catch (Throwable) {
            $normalizedStatus = StatusNormalizer::normalizeStatic($this->status);
        }

        $status = PaymentStatus::tryFromString($normalizedStatus);

        return $status?->isSuccessful() ?? false;
    }

    public function isFailed(): bool
    {
        try {
            if (function_exists('app')) {
                $normalizer = app(StatusNormalizerInterface::class);
                $normalizedStatus = $normalizer->normalize($this->status);
            } else {
                $normalizedStatus = StatusNormalizer::normalizeStatic($this->status);
            }
        } catch (Throwable) {
            $normalizedStatus = StatusNormalizer::normalizeStatic($this->status);
        }

        $status = PaymentStatus::tryFromString($normalizedStatus);

        return $status?->isFailed() ?? false;
    }

    public function isPending(): bool
    {
        try {
            if (function_exists('app')) {
                $normalizer = app(StatusNormalizerInterface::class);
                $normalizedStatus = $normalizer->normalize($this->status);
            } else {
                $normalizedStatus = StatusNormalizer::normalizeStatic($this->status);
            }
        } catch (Throwable) {
            $normalizedStatus = StatusNormalizer::normalizeStatic($this->status);
        }

        $status = PaymentStatus::tryFromString($normalizedStatus);

        return $status?->isPending() ?? false;
    }
}
