<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use KenDeNigerian\PayZephyr\Http\Controllers\WebhookController;
use KenDeNigerian\PayZephyr\PaymentManager;

beforeEach(function () {
    config([
        'payments.logging.enabled' => false,
        'payments.webhook.verify_signature' => false,

        // 🟢 FIX: Define configurations so the drivers can be instantiated without errors
        'payments.providers.monnify' => [
            'driver' => 'monnify',
            'api_key' => 'test_key',
            'secret_key' => 'test_secret',
            'contract_code' => '123',
            'enabled' => true,
        ],
        'payments.providers.stripe' => [
            'driver' => 'stripe',
            'secret_key' => 'test_secret',
            'enabled' => true,
        ],
        'payments.providers.paypal' => [
            'driver' => 'paypal',
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'enabled' => true,
        ],
    ]);
});

test('webhook controller routes monnify requests correctly', function () {
    $manager = app(PaymentManager::class);
    $controller = app(WebhookController::class);

    $payload = ['event' => 'charge.success', 'transactionReference' => 'ref_monnify'];
    $request = Request::create('/payments/webhook/monnify', 'POST', $payload);

    Event::fake();

    $response = $controller->handle($request, 'monnify');

    expect($response->getStatusCode())->toBe(200);
    Event::assertDispatched('payments.webhook.monnify');
});

test('webhook controller routes stripe requests correctly', function () {
    $manager = app(PaymentManager::class);
    $controller = app(WebhookController::class);

    $payload = [
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => ['id' => 'pi_123'],
        ],
    ];
    $request = Request::create('/payments/webhook/stripe', 'POST', $payload);

    Event::fake();

    $response = $controller->handle($request, 'stripe');

    expect($response->getStatusCode())->toBe(200);
    Event::assertDispatched('payments.webhook.stripe');
});

test('webhook controller routes paypal requests correctly', function () {
    $manager = app(PaymentManager::class);
    $controller = app(WebhookController::class);

    $payload = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'pay_123']];
    $request = Request::create('/payments/webhook/paypal', 'POST', $payload);

    Event::fake();

    $response = $controller->handle($request, 'paypal');

    expect($response->getStatusCode())->toBe(200);
    Event::assertDispatched('payments.webhook.paypal');
});
