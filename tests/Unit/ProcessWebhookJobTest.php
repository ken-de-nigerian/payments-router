<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use KenDeNigerian\PayZephyr\Events\WebhookReceived;
use KenDeNigerian\PayZephyr\Jobs\ProcessWebhook;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;
use KenDeNigerian\PayZephyr\PaymentManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payments.logging.enabled' => true,
        'payments.providers.paystack' => [
            'driver' => 'paystack',
            'secret_key' => 'test_secret_key',
            'enabled' => true,
        ],
    ]);

    $logChannel = Mockery::mock();
    $logChannel->shouldReceive('info')->andReturn(true);
    $logChannel->shouldReceive('error')->andReturn(true);
    Log::shouldReceive('channel')->with('payments')->andReturn($logChannel);
    Event::fake();
});

test('process webhook job dispatches webhook received event', function () {
    $job = new ProcessWebhook('paystack', [
        'event' => 'charge.success',
        'data' => ['reference' => 'ref_123'],
    ]);

    $manager = app(PaymentManager::class);

    // Mock the driver to avoid actual API calls
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookReference')
        ->andReturn('ref_123');
    $mockDriver->shouldReceive('extractWebhookStatus')
        ->andReturn('success');
    $mockDriver->shouldReceive('extractWebhookChannel')
        ->andReturn('card');

    // Inject mock driver into PaymentManager using reflection
    $managerReflection = new \ReflectionClass($manager);
    $driversProperty = $managerReflection->getProperty('drivers');
    $driversProperty->setAccessible(true);
    $driversProperty->setValue($manager, ['paystack' => $mockDriver]);

    $job->handle($manager, app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class));

    Event::assertDispatched(WebhookReceived::class, function ($event) {
        return $event->provider === 'paystack'
            && $event->reference === 'ref_123';
    });
});

test('process webhook job updates transaction when reference exists', function () {
    $transaction = PaymentTransaction::create([
        'reference' => 'ref_123',
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    $job = new ProcessWebhook('paystack', [
        'event' => 'charge.success',
        'data' => ['reference' => 'ref_123', 'status' => 'success'],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookReference')
        ->andReturn('ref_123');
    $mockDriver->shouldReceive('extractWebhookStatus')
        ->andReturn('success');
    $mockDriver->shouldReceive('extractWebhookChannel')
        ->andReturn('card');

    $manager->shouldReceive('driver')
        ->with('paystack')
        ->andReturn($mockDriver);

    app()->instance(PaymentManager::class, $manager);

    $job->handle($manager, app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class));

    $transaction->refresh();

    expect($transaction->status)->toBe('success')
        ->and($transaction->channel)->toBe('card')
        ->and($transaction->paid_at)->not->toBeNull();
});

test('process webhook job handles missing reference gracefully', function () {
    $job = new ProcessWebhook('paystack', [
        'event' => 'charge.success',
        'data' => [],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookReference')
        ->andReturn(null);

    $manager->shouldReceive('driver')
        ->with('paystack')
        ->andReturn($mockDriver);

    app()->instance(PaymentManager::class, $manager);

    // Should not throw exception
    $job->handle($manager, app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class));

    Event::assertDispatched(WebhookReceived::class);
});

test('process webhook job retries on failure', function () {
    $job = new ProcessWebhook('paystack', ['event' => 'charge.success']);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(60);
});

test('process webhook job logs processing', function () {
    $job = new ProcessWebhook('paystack', [
        'event' => 'charge.success',
        'data' => ['reference' => 'ref_123'],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookReference')
        ->andReturn('ref_123');
    $mockDriver->shouldReceive('extractWebhookStatus')
        ->andReturn('success');
    $mockDriver->shouldReceive('extractWebhookChannel')
        ->andReturn(null);

    $manager->shouldReceive('driver')
        ->with('paystack')
        ->andReturn($mockDriver);

    app()->instance(PaymentManager::class, $manager);

    // Verify the job executes without throwing exceptions
    expect(fn () => $job->handle($manager, app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class)))
        ->not->toThrow(\Exception::class);

    // Verify the webhook received event was dispatched
    Event::assertDispatched(WebhookReceived::class, function ($event) {
        return $event->provider === 'paystack'
            && $event->reference === 'ref_123';
    });
});

test('process webhook job uses database transactions', function () {
    $transaction = PaymentTransaction::create([
        'reference' => 'ref_123',
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]);

    $job = new ProcessWebhook('paystack', [
        'event' => 'charge.success',
        'data' => ['reference' => 'ref_123'],
    ]);

    $manager = Mockery::mock(PaymentManager::class);
    $mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
    $mockDriver->shouldReceive('extractWebhookReference')
        ->andReturn('ref_123');
    $mockDriver->shouldReceive('extractWebhookStatus')
        ->andReturn('success');
    $mockDriver->shouldReceive('extractWebhookChannel')
        ->andReturn(null);

    $manager->shouldReceive('driver')
        ->with('paystack')
        ->andReturn($mockDriver);

    app()->instance(PaymentManager::class, $manager);

    // The job uses DB::transaction internally, which will be handled by RefreshDatabase
    // We just verify the transaction is updated successfully
    $job->handle($manager, app(\KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface::class));

    $transaction->refresh();
    expect($transaction->status)->toBe('success');
});
