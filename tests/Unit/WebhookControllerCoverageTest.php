<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use KenDeNigerian\PayZephyr\Jobs\ProcessWebhook;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;
use KenDeNigerian\PayZephyr\PaymentManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->forgetInstance('payments.config');

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

test('webhook controller determineStatus handles all provider status formats', function () {
    $statusNormalizer = app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class);

    // Test Paystack
    $job = new ProcessWebhook('paystack', ['data' => ['status' => 'success']]);
    $manager = app(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('success');

    // Inject mock driver into PaymentManager using reflection
    $managerReflection = new \ReflectionClass($manager);
    $driversProperty = $managerReflection->getProperty('drivers');
    $driversProperty->setAccessible(true);
    $driversProperty->setValue($manager, ['paystack' => $mockDriver]);
    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('determineStatus');
    $method->setAccessible(true);
    $status = $method->invoke($job, $manager, $statusNormalizer);
    expect($status)->toBe('success');

    // Test Flutterwave
    $job = new ProcessWebhook('flutterwave', ['data' => ['status' => 'successful']]);
    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('successful');
    $manager->shouldReceive('driver')->with('flutterwave')->andReturn($mockDriver);
    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('determineStatus');
    $method->setAccessible(true);
    $status = $method->invoke($job, $manager, $statusNormalizer);
    expect($status)->toBe('success');

    // Test Monnify
    $job = new ProcessWebhook('monnify', ['paymentStatus' => 'PAID']);
    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('PAID');
    $manager->shouldReceive('driver')->with('monnify')->andReturn($mockDriver);
    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('determineStatus');
    $method->setAccessible(true);
    $status = $method->invoke($job, $manager, $statusNormalizer);
    expect($status)->toBe('success');

    // Test Stripe
    $job = new ProcessWebhook('stripe', ['data' => ['object' => ['status' => 'succeeded']]]);
    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('succeeded');
    $manager->shouldReceive('driver')->with('stripe')->andReturn($mockDriver);
    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('determineStatus');
    $method->setAccessible(true);
    $status = $method->invoke($job, $manager, $statusNormalizer);
    expect($status)->toBe('success');

    // Test PayPal
    $job = new ProcessWebhook('paypal', ['resource' => ['status' => 'COMPLETED']]);
    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('COMPLETED');
    $manager->shouldReceive('driver')->with('paypal')->andReturn($mockDriver);
    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('determineStatus');
    $method->setAccessible(true);
    $status = $method->invoke($job, $manager, $statusNormalizer);
    expect($status)->toBe('success');

    // Test unknown provider
    $job = new ProcessWebhook('unknown', []);
    $manager = Mockery::mock(PaymentManager::class);
    $manager->shouldReceive('driver')
        ->with('unknown')
        ->andThrow(new \KenDeNigerian\PayZephyr\Exceptions\DriverNotFoundException('Driver not found'));
    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('determineStatus');
    $method->setAccessible(true);
    $status = $method->invoke($job, $manager, $statusNormalizer);
    expect($status)->toBe('unknown');
});

test('webhook controller updateTransactionFromWebhook updates with channel', function () {
    config(['payments.logging.enabled' => true]);

    PaymentTransaction::create([
        'reference' => 'ref_123',
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 1000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    $job = new ProcessWebhook('paystack', [
        'data' => ['status' => 'success', 'channel' => 'card'],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('success');
    $mockDriver->shouldReceive('extractWebhookChannel')->andReturn('card');
    $manager->shouldReceive('driver')->with('paystack')->andReturn($mockDriver);

    $statusNormalizer = app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class);

    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('updateTransactionFromWebhook');
    $method->setAccessible(true);

    $method->invoke($job, $manager, $statusNormalizer, 'ref_123');

    $transaction = PaymentTransaction::where('reference', 'ref_123')->first();

    expect($transaction->status)->toBe('success')
        ->and($transaction->channel)->toBe('card')
        ->and($transaction->paid_at)->not->toBeNull();
});

test('webhook controller updateTransactionFromWebhook handles database error gracefully', function () {
    config(['payments.logging.enabled' => true]);

    $job = new ProcessWebhook('paystack', [
        'data' => ['status' => 'success'],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('success');
    $mockDriver->shouldReceive('extractWebhookChannel')->andReturn(null);
    $manager->shouldReceive('driver')->with('paystack')->andReturn($mockDriver);

    $statusNormalizer = app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class);

    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('updateTransactionFromWebhook');
    $method->setAccessible(true);

    // Should not throw exception even if transaction doesn't exist
    $method->invoke($job, $manager, $statusNormalizer, 'nonexistent_ref');

    expect(true)->toBeTrue();
});

test('webhook controller updateTransactionFromWebhook handles different provider channels', function () {
    config(['payments.logging.enabled' => true]);

    PaymentTransaction::create([
        'reference' => 'ref_123',
        'provider' => 'flutterwave',
        'status' => 'pending',
        'amount' => 1000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    $job = new ProcessWebhook('flutterwave', [
        'data' => ['status' => 'successful', 'payment_type' => 'card'],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('successful');
    $mockDriver->shouldReceive('extractWebhookChannel')->andReturn('card');
    $manager->shouldReceive('driver')->with('flutterwave')->andReturn($mockDriver);

    $statusNormalizer = app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class);

    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('updateTransactionFromWebhook');
    $method->setAccessible(true);

    $method->invoke($job, $manager, $statusNormalizer, 'ref_123');

    $transaction = PaymentTransaction::where('reference', 'ref_123')->first();

    expect($transaction->channel)->toBe('card');
});

test('webhook controller updateTransactionFromWebhook handles monnify channel', function () {
    config(['payments.logging.enabled' => true]);

    PaymentTransaction::create([
        'reference' => 'ref_123',
        'provider' => 'monnify',
        'status' => 'pending',
        'amount' => 1000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    $job = new ProcessWebhook('monnify', [
        'paymentStatus' => 'PAID',
        'paymentMethod' => 'CARD',
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('PAID');
    $mockDriver->shouldReceive('extractWebhookChannel')->andReturn('CARD');
    $manager->shouldReceive('driver')->with('monnify')->andReturn($mockDriver);

    $statusNormalizer = app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class);

    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('updateTransactionFromWebhook');
    $method->setAccessible(true);

    $method->invoke($job, $manager, $statusNormalizer, 'ref_123');

    $transaction = PaymentTransaction::where('reference', 'ref_123')->first();

    expect($transaction->channel)->toBe('CARD');
});

test('webhook controller updateTransactionFromWebhook handles stripe channel', function () {
    config(['payments.logging.enabled' => true]);

    PaymentTransaction::create([
        'reference' => 'ref_123',
        'provider' => 'stripe',
        'status' => 'pending',
        'amount' => 1000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    $job = new ProcessWebhook('stripe', [
        'data' => [
            'object' => [
                'status' => 'succeeded',
                'payment_method' => 'card',
            ],
        ],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('succeeded');
    $mockDriver->shouldReceive('extractWebhookChannel')->andReturn('card');
    $manager->shouldReceive('driver')->with('stripe')->andReturn($mockDriver);

    $statusNormalizer = app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class);

    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('updateTransactionFromWebhook');
    $method->setAccessible(true);

    $method->invoke($job, $manager, $statusNormalizer, 'ref_123');

    $transaction = PaymentTransaction::where('reference', 'ref_123')->first();

    expect($transaction->channel)->toBe('card');
});

test('webhook controller updateTransactionFromWebhook handles paypal channel', function () {
    config(['payments.logging.enabled' => true]);

    PaymentTransaction::create([
        'reference' => 'ref_123',
        'provider' => 'paypal',
        'status' => 'pending',
        'amount' => 1000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    $job = new ProcessWebhook('paypal', [
        'resource' => ['status' => 'COMPLETED'],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookStatus')->andReturn('COMPLETED');
    $mockDriver->shouldReceive('extractWebhookChannel')->andReturn('paypal');
    $manager->shouldReceive('driver')->with('paypal')->andReturn($mockDriver);

    $statusNormalizer = app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class);

    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('updateTransactionFromWebhook');
    $method->setAccessible(true);

    $method->invoke($job, $manager, $statusNormalizer, 'ref_123');

    $transaction = PaymentTransaction::where('reference', 'ref_123')->first();

    expect($transaction->channel)->toBe('paypal');
});
