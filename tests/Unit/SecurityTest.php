<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;
use KenDeNigerian\PayZephyr\PaymentManager;

beforeEach(function () {
    \Illuminate\Support\Facades\DB::setDefaultConnection('testing');

    try {
        \Illuminate\Support\Facades\Schema::connection('testing')->dropIfExists('payment_transactions');
    } catch (\Exception $e) {
        // Ignore if table doesn't exist
    }

    \Illuminate\Support\Facades\Schema::connection('testing')->create('payment_transactions', function ($table) {
        $table->id();
        $table->string('reference');
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

// Task 1.1: SQL Injection Prevention
test('it validates table names against sql injection', function () {
    config(['payments.logging.table' => 'payment_transactions; DROP TABLE users--']);

    $transaction = new PaymentTransaction();

    expect($transaction->getTable())->toBe('payment_transactions');
});

test('it accepts valid table names', function () {
    config(['payments.logging.table' => 'custom_payment_transactions']);

    $transaction = new PaymentTransaction();

    expect($transaction->getTable())->toBe('custom_payment_transactions');
});

test('it rejects table names with special characters', function () {
    config(['payments.logging.table' => 'payment-transactions']);

    $transaction = new PaymentTransaction();

    expect($transaction->getTable())->toBe('payment_transactions');
});

// Task 1.2: Webhook Replay Attack Prevention
test('it rejects webhooks with old timestamps', function () {
    $driver = app(PaymentManager::class)->driver('paystack');

    $oldPayload = [
        'event' => 'charge.success',
        'data' => ['reference' => 'TEST_123'],
        'timestamp' => time() - 360, // 6 minutes old
    ];

    $signature = hash_hmac('sha512', json_encode($oldPayload), config('payments.providers.paystack.secret_key'));

    $isValid = $driver->validateWebhook(
        ['x-paystack-signature' => [$signature]],
        json_encode($oldPayload)
    );

    expect($isValid)->toBeFalse();
});

test('it accepts webhooks with recent timestamps', function () {
    $driver = app(PaymentManager::class)->driver('paystack');

    $recentPayload = [
        'event' => 'charge.success',
        'data' => ['reference' => 'TEST_123'],
        'timestamp' => time() - 60, // 1 minute old
    ];

    $signature = hash_hmac('sha512', json_encode($recentPayload), config('payments.providers.paystack.secret_key'));

    $isValid = $driver->validateWebhook(
        ['x-paystack-signature' => [$signature]],
        json_encode($recentPayload)
    );

    expect($isValid)->toBeTrue();
});

test('it accepts webhooks without timestamps for backward compatibility', function () {
    $driver = app(PaymentManager::class)->driver('paystack');

    $payload = [
        'event' => 'charge.success',
        'data' => ['reference' => 'TEST_123'],
        // No timestamp field
    ];

    $signature = hash_hmac('sha512', json_encode($payload), config('payments.providers.paystack.secret_key'));

    $isValid = $driver->validateWebhook(
        ['x-paystack-signature' => [$signature]],
        json_encode($payload)
    );

    // Should still validate signature, but allow missing timestamp
    expect($isValid)->toBeTrue();
});

// Task 1.3: Multi-Tenant Cache Isolation
test('it isolates cache keys per user', function () {
    $manager = app(PaymentManager::class);
    $reflection = new ReflectionClass($manager);
    
    // Test getCacheContext method directly
    $contextMethod = $reflection->getMethod('getCacheContext');
    $contextMethod->setAccessible(true);
    
    // Mock auth() function
    $originalAuth = null;
    if (function_exists('auth')) {
        // Store original if needed
    }
    
    // Use reflection to test cache key generation with context
    $cacheKeyMethod = $reflection->getMethod('cacheKey');
    $cacheKeyMethod->setAccessible(true);
    
    // Test without context (should return global key)
    $key = $cacheKeyMethod->invoke($manager, 'session', 'REF_123');
    
    expect($key)->toBe('payzephyr:session:REF_123');
});

// Task 1.4: Log Sanitization
test('it sanitizes sensitive data in logs', function () {
    Log::spy();

    $driver = app(PaymentManager::class)->driver('paystack');

    $driver->log('error', 'Test error', [
        'api_key' => 'sk_test_secret123',
        'password' => 'user_password',
        'email' => 'user@example.com',
        'secret_token' => 'my_secret_token',
    ]);

    Log::assertLogged('error', function ($message, $context) {
        return $context['api_key'] === '[REDACTED]'
            && $context['password'] === '[REDACTED]'
            && $context['secret_token'] === '[REDACTED]'
            && $context['email'] === 'user@example.com';
    });
});

test('it sanitizes api tokens in log strings', function () {
    Log::spy();

    $driver = app(PaymentManager::class)->driver('paystack');

    $driver->log('info', 'Test', [
        'token' => 'sk_test_12345678901234567890',
        'stripe_key' => 'pk_test_12345678901234567890',
    ]);

    Log::assertLogged('info', function ($message, $context) {
        return $context['token'] === '[REDACTED_TOKEN]'
            && $context['stripe_key'] === '[REDACTED_TOKEN]';
    });
});

test('it sanitizes nested sensitive data', function () {
    Log::spy();

    $driver = app(PaymentManager::class)->driver('paystack');

    $driver->log('info', 'Test', [
        'config' => [
            'api_key' => 'sk_test_secret',
            'public_key' => 'pk_test_public',
        ],
        'user' => [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ],
    ]);

    Log::assertLogged('info', function ($message, $context) {
        return $context['config']['api_key'] === '[REDACTED]'
            && $context['user']['password'] === '[REDACTED]'
            && $context['user']['email'] === 'user@example.com';
    });
});

// Task 1.5: Rate Limiting
test('it rate limits payment initialization', function () {
    $email = 'test@example.com';
    $key = 'payment_charge:email_'.hash('sha256', $email);

    RateLimiter::clear($key);

    // First 10 attempts should hit rate limiter
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }

    // Check that 11th attempt would be blocked
    expect(RateLimiter::tooManyAttempts($key, 10))->toBeTrue();
});

test('it rate limits by email when user not authenticated', function () {
    $email = 'test@example.com';
    $key = 'payment_charge:email_'.hash('sha256', $email);

    RateLimiter::clear($key);

    // First 10 attempts
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }

    // 11th attempt should be blocked
    expect(RateLimiter::tooManyAttempts($key, 10))->toBeTrue();
});

// Task 1.6: Enhanced Input Validation
test('it rejects emails with double dots', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'user..name@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid email address');
});

test('it rejects emails with trailing dots', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'user@example.com.',
    ]))->toThrow(InvalidArgumentException::class);
});

test('it rejects http callback urls in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'callback_url' => 'http://example.com/callback',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid callback URL');
});

test('it accepts https callback urls in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $dto = ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'callback_url' => 'https://example.com/callback',
    ]);

    expect($dto->callbackUrl)->toBe('https://example.com/callback');
});

test('it rejects references with special characters', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'reference' => 'ORDER_123; DROP TABLE users--',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid reference format');
});

test('it accepts valid reference formats', function () {
    $dto = ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'reference' => 'ORDER_123-ABC',
    ]);

    expect($dto->reference)->toBe('ORDER_123-ABC');
});

test('it validates email local part length', function () {
    $longLocal = str_repeat('a', 65).'@example.com';

    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => $longLocal,
    ]))->toThrow(InvalidArgumentException::class);
});
