<?php

use KenDeNigerian\PayZephyr\DataObjects\ChargeRequest;
use KenDeNigerian\PayZephyr\Drivers\StripeDriver;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;
use Stripe\Exception\InvalidRequestException;

// Helper to create a fake StripeClient/Service structure
function createMockStripeDriver(object $stripeMock): StripeDriver
{
    $config = [
        'secret_key' => 'sk_test_xxx',
        'currencies' => ['USD', 'EUR', 'GBP', 'NGN'],
        'callback_url' => 'https://example.com/callback',
    ];

    $driver = new class($config) extends StripeDriver
    {
        // We override this to avoid constructing the real StripeClient
        protected function initializeClient(): void
        {
            // No-op for the real client
        }
    };

    // Inject our mock
    $driver->setStripeClient($stripeMock);

    return $driver;
}

test('stripe charge succeeds', function () {
    // Mock the Intent object
    $intentMock = (object) [
        'id' => 'pi_test_123',
        'client_secret' => 'secret_123',
        'status' => 'pending', // 'processing' or 'requires_payment_method' usually
    ];

    // Mock the paymentIntents service
    $paymentIntents = new class($intentMock)
    {
        public function __construct(private readonly object $intent) {}

        public function create()
        {
            return $this->intent;
        }
    };

    // Mock the StripeClient
    $stripeMock = new class($paymentIntents)
    {
        public function __construct(public object $paymentIntents) {}
    };

    $driver = createMockStripeDriver($stripeMock);

    $request = new ChargeRequest(10000, 'USD', 'test@example.com', 'stripe_ref_123');
    $response = $driver->charge($request);

    expect($response->reference)->toBe('stripe_ref_123')
        ->and($response->authorizationUrl)->toBe('secret_123')
        ->and($response->status)->toBe('pending');
});

test('stripe charge handles api error', function () {
    // Mock throwing exception
    $paymentIntents = new class
    {
        public function create()
        {
            throw new InvalidRequestException('Invalid currency', 400);
        }
    };

    $stripeMock = new class($paymentIntents)
    {
        public function __construct(public object $paymentIntents) {}
    };

    $driver = createMockStripeDriver($stripeMock);

    // This should fail with InvalidArgumentException because ChargeRequest validates first!
    $driver->charge(new ChargeRequest(10000, 'INVALID', 'test@example.com'));
})->throws(InvalidArgumentException::class);

test('stripe verify returns success', function () {
    $intentMock = (object) [
        'id' => 'pi_test_123',
        'status' => 'succeeded',
        'amount' => 1000000,
        'currency' => 'usd',
        'created' => time(),
        'metadata' => ['reference' => 'stripe_ref_123'],
        'payment_method' => 'pm_123',
        'receipt_email' => 'test@example.com',
    ];

    $paymentIntents = new class($intentMock)
    {
        public function __construct(private readonly object $intent) {}

        public function retrieve()
        {
            return $this->intent;
        }
    };

    $stripeMock = new class($paymentIntents)
    {
        public function __construct(public object $paymentIntents) {}
    };

    $driver = createMockStripeDriver($stripeMock);
    $result = $driver->verify('stripe_ref_123');

    expect($result->status)->toBe('success')
        ->and($result->amount)->toBe(10000.0)
        ->and($result->isSuccessful())->toBeTrue();
});

test('stripe verify returns failed', function () {
    $intentMock = (object) [
        'id' => 'pi_test_123',
        'status' => 'canceled',
        'amount' => 1000000,
        'currency' => 'usd',
        'metadata' => ['reference' => 'stripe_failed'],
        'receipt_email' => 'test@example.com',
    ];

    $paymentIntents = new class($intentMock)
    {
        public function __construct(private readonly object $intent) {}

        public function retrieve()
        {
            return $this->intent;
        }
    };

    $stripeMock = new class($paymentIntents)
    {
        public function __construct(public object $paymentIntents) {}
    };

    $driver = createMockStripeDriver($stripeMock);
    $result = $driver->verify('stripe_failed');

    expect($result->isFailed())->toBeTrue();
});

test('stripe verify handles not found', function () {
    $paymentIntents = new class
    {
        public function retrieve()
        {
            throw new InvalidRequestException('No such payment_intent', 404);
        }

        public function all(): object
        {
            // Emulate finding nothing by metadata either
            return (object) ['data' => []];
        }
    };

    $stripeMock = new class($paymentIntents)
    {
        public function __construct(public object $paymentIntents) {}
    };

    $driver = createMockStripeDriver($stripeMock);

    $driver->verify('stripe_nonexistent');
})->throws(VerificationException::class);
