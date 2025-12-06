<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Payment Transaction Model
 *
 * @property int $id
 * @property string $reference
 * @property string $provider
 * @property string $status
 * @property float $amount
 * @property string $currency
 * @property string $email
 * @property string|null $channel
 * @property array|null $metadata
 * @property array|null $customer
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static create(array $array)
 * @method static where(string $string, string $reference)
 * @method static successful()
 * @method static failed()
 * @method static pending()
 */
class PaymentTransaction extends Model
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'customer' => 'array',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('payments.logging.table') ?? 'payment_transactions';
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName(): ?string
    {
        // Use the default connection if set, otherwise use testing in test environment
        return parent::getConnectionName() ?? (app()->environment('testing') ? 'testing' : null);
    }

    /**
     * Scope a query to only include successful payments.
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', ['success', 'succeeded', 'completed', 'successful']);
    }

    /**
     * Scope a query to only include failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'cancelled', 'declined']);
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if payment is successful.
     */
    public function isSuccessful(): bool
    {
        return in_array(strtolower($this->status), ['success', 'succeeded', 'completed', 'successful']);
    }

    /**
     * Check if payment has failed.
     */
    public function isFailed(): bool
    {
        return in_array(strtolower($this->status), ['failed', 'cancelled', 'declined']);
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return strtolower($this->status) === 'pending';
    }
}
