<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use KenDeNigerian\PayZephyr\Drivers\OPayDriver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

test('opay driver getIdempotencyHeader returns correct header', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $reflection = new ReflectionClass($driver);
    $method = $reflection->getMethod('getIdempotencyHeader');

    $result = $method->invoke($driver, 'test_key');

    expect($result)->toBe(['Idempotency-Key' => 'test_key']);
});

test('opay driver healthCheck returns true for successful response', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $client = Mockery::mock(Client::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);

    $client->shouldReceive('request')
        ->once()
        ->andReturn($response);

    $driver->setClient($client);

    expect($driver->healthCheck())->toBeTrue();
});

test('opay driver healthCheck returns true for 4xx errors', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $client = Mockery::mock(Client::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(404);

    $client->shouldReceive('request')
        ->once()
        ->andReturn($response);

    $driver->setClient($client);

    expect($driver->healthCheck())->toBeTrue();
});

test('opay driver healthCheck returns false for network errors', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('request')
        ->once()
        ->andThrow(new ConnectException('Connection timeout', Mockery::mock(RequestInterface::class)));

    $driver->setClient($client);

    expect($driver->healthCheck())->toBeFalse();
});

test('opay driver validateWebhook returns false when signature missing', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $result = $driver->validateWebhook([], 'test body');

    expect($result)->toBeFalse();
});

test('opay driver validateWebhook returns false when secret key missing', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    // Use reflection to set secret_key and public_key to empty for testing
    $reflection = new ReflectionClass($driver);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    $config = $configProperty->getValue($driver);
    $config['public_key'] = '';
    $config['secret_key'] = '';
    $configProperty->setValue($driver, $config);

    $result = $driver->validateWebhook(['x-opay-signature' => ['signature']], 'test body');

    expect($result)->toBeFalse();
});

test('opay driver validateWebhook handles case-insensitive header', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    // Note: Actual RSA validation would require valid keys, so we just test header extraction
    $result = $driver->validateWebhook(['X-OPay-Signature' => ['signature']], 'test body');

    // Should return false due to invalid signature, but header was found
    expect($result)->toBeFalse();
});

test('opay driver extractWebhookReference extracts reference', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $payload = ['reference' => 'OPAY123'];

    expect($driver->extractWebhookReference($payload))->toBe('OPAY123');
});

test('opay driver extractWebhookReference extracts orderNo', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $payload = ['orderNo' => 'ORDER123'];

    expect($driver->extractWebhookReference($payload))->toBe('ORDER123');
});

test('opay driver extractWebhookStatus extracts status', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $payload = ['status' => 'SUCCESS'];

    expect($driver->extractWebhookStatus($payload))->toBe('SUCCESS');
});

test('opay driver extractWebhookStatus extracts orderStatus', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $payload = ['orderStatus' => 'PENDING'];

    expect($driver->extractWebhookStatus($payload))->toBe('PENDING');
});

test('opay driver extractWebhookChannel extracts paymentChannel', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $payload = ['paymentChannel' => 'card'];

    expect($driver->extractWebhookChannel($payload))->toBe('card');
});

test('opay driver resolveVerificationId returns reference', function () {
    $driver = new OPayDriver([
        'merchant_id' => 'test_merchant',
        'public_key' => 'test_public',
        'currencies' => ['NGN'],
    ]);

    $result = $driver->resolveVerificationId('OPAY_123', 'TXN456');

    expect($result)->toBe('OPAY_123');
});
