<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;

beforeEach(function () {
    \Illuminate\Support\Facades\DB::setDefaultConnection('testing');

    try {
        \Illuminate\Support\Facades\Schema::connection('testing')->dropIfExists('payment_transactions');
    } catch (\Exception $e) {
        // Ignore if table doesn't exist
    }

    \Illuminate\Support\Facades\Schema::connection('testing')->create('payment_transactions', function ($table) {
        $table->id();
        $table->string('reference')->unique();
        $table->string('provider');
        $table->string('status');
        $table->decimal('amount', 15, 2);
        $table->string('currency');
        $table->string('email');
        $table->string('channel')->nullable();
        $table->json('metadata')->nullable();
        $table->json('customer')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->timestamps();
    });
});

test('it prevents race conditions in transaction updates', function () {
    $transaction = PaymentTransaction::create([
        'reference' => 'TEST_123',
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    // Simulate concurrent updates using database locks
    DB::transaction(function () use ($transaction) {
        $t = PaymentTransaction::where('reference', 'TEST_123')
            ->lockForUpdate()
            ->first();

        expect($t)->not->toBeNull();
        $t->update(['status' => 'success']);
    });

    // Verify update succeeded
    $transaction->refresh();
    expect($transaction->status)->toBe('success');
});

test('it handles concurrent webhook and verification updates', function () {
    $transaction = PaymentTransaction::create([
        'reference' => 'TEST_456',
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 20000,
        'currency' => 'NGN',
        'email' => 'test2@example.com',
    ]);

    // Simulate webhook update
    DB::transaction(function () {
        $t = PaymentTransaction::where('reference', 'TEST_456')
            ->lockForUpdate()
            ->first();

        if ($t && $t->status !== 'success') {
            $t->update(['status' => 'success', 'paid_at' => now()]);
        }
    });

    // Simulate verification update (should be idempotent)
    DB::transaction(function () {
        $t = PaymentTransaction::where('reference', 'TEST_456')
            ->lockForUpdate()
            ->first();

        if ($t && $t->status !== 'success') {
            $t->update(['status' => 'success', 'paid_at' => now()]);
        }
    });

    // Verify final state
    $transaction->refresh();
    expect($transaction->status)->toBe('success')
        ->and($transaction->paid_at)->not->toBeNull();
});

test('it prevents duplicate webhook processing', function () {
    $transaction = PaymentTransaction::create([
        'reference' => 'TEST_789',
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 30000,
        'currency' => 'NGN',
        'email' => 'test3@example.com',
    ]);

    $updateCount = 0;

    // Simulate same webhook arriving twice
    for ($i = 0; $i < 2; $i++) {
        DB::transaction(function () use (&$updateCount) {
            $t = PaymentTransaction::where('reference', 'TEST_789')
                ->lockForUpdate()
                ->first();

            // Only update if still pending (idempotent check)
            if ($t && $t->status === 'pending') {
                $t->update(['status' => 'success']);
                $updateCount++;
            }
        });
    }

    // Should only update once
    expect($updateCount)->toBe(1);

    $transaction->refresh();
    expect($transaction->status)->toBe('success');
});

test('it handles concurrent cache writes safely', function () {
    $reference = 'CACHE_TEST_123';
    $key = "payzephyr:session:{$reference}";

    Cache::flush();

    // Simulate concurrent cache writes
    for ($i = 0; $i < 5; $i++) {
        Cache::put($key, [
            'provider' => 'paystack',
            'id' => "provider_id_{$i}",
        ], now()->addHour());
    }

    // Cache should have final value (last write wins)
    $cached = Cache::get($key);
    expect($cached)->toBeArray()
        ->and($cached['provider'])->toBe('paystack');
});

test('it handles concurrent verification requests', function () {
    $transaction = PaymentTransaction::create([
        'reference' => 'VERIFY_TEST',
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 50000,
        'currency' => 'NGN',
        'email' => 'verify@example.com',
    ]);

    $verifyCount = 0;

    // Simulate multiple verification requests
    for ($i = 0; $i < 3; $i++) {
        DB::transaction(function () use (&$verifyCount) {
            $t = PaymentTransaction::where('reference', 'VERIFY_TEST')
                ->lockForUpdate()
                ->first();

            if ($t && ! $t->isSuccessful()) {
                $t->update(['status' => 'success']);
                $verifyCount++;
            }
        });
    }

    // Should only update once (idempotent)
    expect($verifyCount)->toBe(1);

    $transaction->refresh();
    expect($transaction->status)->toBe('success');
});
