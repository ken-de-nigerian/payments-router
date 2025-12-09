<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use KenDeNigerian\PayZephyr\Drivers\RemitaDriver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

test('remita driver getIdempotencyHeader returns correct header', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $reflection = new ReflectionClass($driver);
    $method = $reflection->getMethod('getIdempotencyHeader');

    $result = $method->invoke($driver, 'test_key');

    expect($result)->toBe(['Idempotency-Key' => 'test_key']);
});

test('remita driver healthCheck returns true for successful response', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
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

test('remita driver healthCheck returns true for 4xx errors', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $client = Mockery::mock(Client::class);
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(404);

    $client->shouldReceive('request')
        ->once()
        ->andThrow(new ClientException('Not Found', $request, $response));

    $driver->setClient($client);

    expect($driver->healthCheck())->toBeTrue();
});

test('remita driver healthCheck returns false for network errors', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('request')
        ->once()
        ->andThrow(new ConnectException('Connection timeout', Mockery::mock(RequestInterface::class)));

    $driver->setClient($client);

    expect($driver->healthCheck())->toBeFalse();
});

test('remita driver validateWebhook returns false when signature missing', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $result = $driver->validateWebhook([], 'test body');

    expect($result)->toBeFalse();
});

test('remita driver validateWebhook returns false when secret key missing', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    // Use reflection to set secret_key to empty for testing
    $reflection = new ReflectionClass($driver);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    $config = $configProperty->getValue($driver);
    $config['secret_key'] = '';
    $configProperty->setValue($driver, $config);

    $result = $driver->validateWebhook(['remita-hash' => ['signature']], 'test body');

    expect($result)->toBeFalse();
});

test('remita driver validateWebhook validates correct signature', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret_key',
        'currencies' => ['NGN'],
    ]);

    $body = '{"test": "data"}';
    $expectedHash = hash('sha512', $body.'test_secret_key');

    $result = $driver->validateWebhook(['remita-hash' => [$expectedHash]], $body);

    expect($result)->toBeTrue();
});

test('remita driver validateWebhook rejects invalid signature', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret_key',
        'currencies' => ['NGN'],
    ]);

    $body = '{"test": "data"}';
    $invalidHash = 'invalid_signature';

    $result = $driver->validateWebhook(['remita-hash' => [$invalidHash]], $body);

    expect($result)->toBeFalse();
});

test('remita driver validateWebhook handles case-insensitive header', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret_key',
        'currencies' => ['NGN'],
    ]);

    $body = '{"test": "data"}';
    $expectedHash = hash('sha512', $body.'test_secret_key');

    $result = $driver->validateWebhook(['Remita-Hash' => [$expectedHash]], $body);

    expect($result)->toBeTrue();
});

test('remita driver extractWebhookReference extracts orderId', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $payload = ['orderId' => 'ORDER123'];

    expect($driver->extractWebhookReference($payload))->toBe('ORDER123');
});

test('remita driver extractWebhookReference extracts rrr', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $payload = ['rrr' => 'RRR123'];

    expect($driver->extractWebhookReference($payload))->toBe('RRR123');
});

test('remita driver extractWebhookStatus extracts status', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $payload = ['status' => '00'];

    expect($driver->extractWebhookStatus($payload))->toBe('00');
});

test('remita driver extractWebhookStatus extracts statusCode', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $payload = ['statusCode' => '01'];

    expect($driver->extractWebhookStatus($payload))->toBe('01');
});

test('remita driver extractWebhookChannel extracts paymentChannel', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $payload = ['paymentChannel' => 'card'];

    expect($driver->extractWebhookChannel($payload))->toBe('card');
});

test('remita driver resolveVerificationId returns providerId when available', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $result = $driver->resolveVerificationId('REMITA_123', 'RRR456');

    expect($result)->toBe('RRR456');
});

test('remita driver resolveVerificationId returns reference when providerId empty', function () {
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);

    $result = $driver->resolveVerificationId('REMITA_123', '');

    expect($result)->toBe('REMITA_123');
});
