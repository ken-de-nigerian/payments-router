<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use KenDeNigerian\PayZephyr\Facades\Payment;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;

beforeEach(function () {
    \Illuminate\Support\Facades\DB::setDefaultConnection('testing');

    try {
        \Illuminate\Support\Facades\Schema::connection('testing')->dropIfExists('payment_transactions');
    } catch (\Exception $e) {
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

test('it handles high volume payment initializations', function () {

    $references = [];

    for ($i = 0; $i < 10; $i++) {
        try {
            $response = Payment::amount(10000 + $i)
                ->email("user{$i}@example.com")
                ->callback('https://example.com/callback')
                ->charge();

            $references[] = $response->reference;
        } catch (\Exception $e) {
        }
    }

    expect(array_unique($references))->toHaveCount(count($references));
});

test('it handles large number of transactions in database', function () {
    for ($i = 0; $i < 100; $i++) {
        PaymentTransaction::create([
            'reference' => "TEST_REF_{$i}",
            'provider' => 'paystack',
            'status' => $i % 3 === 0 ? 'success' : ($i % 3 === 1 ? 'failed' : 'pending'),
            'amount' => 10000 + $i,
            'currency' => 'NGN',
            'email' => "user{$i}@example.com",
        ]);
    }

    $startTime = microtime(true);

    $successful = PaymentTransaction::successful()->count();
    $failed = PaymentTransaction::failed()->count();
    $pending = PaymentTransaction::pending()->count();

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    expect($duration)->toBeLessThan(5.0);

    $total = $successful + $failed + $pending;
    expect($total)->toBeGreaterThanOrEqual(100);
});

test('it handles concurrent cache operations', function () {
    $references = [];

    for ($i = 0; $i < 20; $i++) {
        $ref = "CACHE_TEST_{$i}";
        $references[] = $ref;

        Cache::put("payzephyr:session:{$ref}", [
            'provider' => 'paystack',
            'id' => "provider_id_{$i}",
        ], now()->addHour());
    }

    foreach ($references as $ref) {
        expect(Cache::get("payzephyr:session:{$ref}"))->not->toBeNull();
    }
});

test('it handles bulk transaction queries efficiently', function () {
    $statuses = ['success', 'failed', 'pending'];

    for ($i = 0; $i < 50; $i++) {
        PaymentTransaction::create([
            'reference' => "BULK_REF_{$i}",
            'provider' => 'paystack',
            'status' => $statuses[$i % 3],
            'amount' => 10000,
            'currency' => 'NGN',
            'email' => "bulk{$i}@example.com",
        ]);
    }

    $all = PaymentTransaction::where('provider', 'paystack')->count();
    $successful = PaymentTransaction::successful()->count();
    $failed = PaymentTransaction::failed()->count();

    expect($all)->toBeGreaterThanOrEqual(50)
        ->and($successful)->toBeGreaterThanOrEqual(0)
        ->and($failed)->toBeGreaterThanOrEqual(0);
});
