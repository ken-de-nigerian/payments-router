<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\Drivers\RemitaDriver;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;

function createRemitaDriverWithMock(array $responses): RemitaDriver
{
    $config = [
        'public_key' => 'PUBLIC_KEY_123',
        'secret_key' => 'SECRET_KEY_123',
        'base_url' => 'https://api.remita.net',
        'currencies' => ['NGN'],
    ];

    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    $driver = new RemitaDriver($config);
    $driver->setClient($client);

    return $driver;
}

test('remita charges successfully and returns RRR', function () {
    $driver = createRemitaDriverWithMock([
        new Response(200, [], json_encode([
            'statusCode' => '00',
            'statusMessage' => 'Success',
            'RRR' => 'RRR123456789',
        ])),
    ]);

    $request = new ChargeRequestDTO(20000, 'NGN', 'test@example.com', null, 'https://example.com/callback');
    $response = $driver->charge($request);

    expect($response->reference)->toStartWith('REMITA_')
        ->and($response->accessCode)->toBe('RRR123456789')
        ->and($response->status)->toBe('pending')
        ->and($response->authorizationUrl)->toContain('RRR123456789');
});

test('remita charge handles statusCode 01 as success', function () {
    $driver = createRemitaDriverWithMock([
        new Response(200, [], json_encode([
            'statusCode' => '01',
            'statusMessage' => 'Pending',
            'RRR' => 'RRR987654321',
        ])),
    ]);

    $request = new ChargeRequestDTO(10000, 'NGN', 'test@example.com');
    $response = $driver->charge($request);

    expect($response->accessCode)->toBe('RRR987654321');
});

test('remita charge throws exception when statusCode is not 00 or 01', function () {
    $driver = createRemitaDriverWithMock([
        new Response(200, [], json_encode([
            'statusCode' => '02',
            'statusMessage' => 'Failed',
        ])),
    ]);

    $request = new ChargeRequestDTO(10000, 'NGN', 'test@example.com');

    $driver->charge($request);
})->throws(ChargeException::class);

test('remita charge throws exception when RRR missing', function () {
    $driver = createRemitaDriverWithMock([
        new Response(200, [], json_encode([
            'statusCode' => '00',
            'statusMessage' => 'Success',
        ])),
    ]);

    $request = new ChargeRequestDTO(10000, 'NGN', 'test@example.com');

    $driver->charge($request);
})->throws(ChargeException::class, 'Failed to generate Remita RRR');

test('remita charge handles network error', function () {
    $mock = new MockHandler([
        new ConnectException('Timeout', new Request('POST', '/remita/exapp/api/v1/send/api/echannelsvc/merchant/api/paymentinit')),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);
    $driver->setClient($client);

    $request = new ChargeRequestDTO(10000, 'NGN', 'test@example.com');

    $driver->charge($request);
})->throws(ChargeException::class);

test('remita verify returns success for statusCode 00', function () {
    $driver = createRemitaDriverWithMock([
        new Response(200, [], json_encode([
            'statusCode' => '00',
            'statusMessage' => 'Success',
            'responseData' => [
                'orderId' => 'REMITA_123',
                'rrr' => 'RRR123',
                'amount' => 20000,
                'currency' => 'NGN',
                'status' => '00',
                'paymentDate' => '2024-01-01T12:00:00Z',
                'payerEmail' => 'test@example.com',
                'payerName' => 'Test User',
            ],
        ])),
    ]);

    $result = $driver->verify('RRR123');

    expect($result->status)->toBe('success')
        ->and($result->amount)->toBe(200.0)
        ->and($result->currency)->toBe('NGN');
});

test('remita verify returns pending for statusCode 01', function () {
    $driver = createRemitaDriverWithMock([
        new Response(200, [], json_encode([
            'statusCode' => '00',
            'statusMessage' => 'Success',
            'responseData' => [
                'orderId' => 'REMITA_123',
                'rrr' => 'RRR123',
                'amount' => 20000,
                'status' => '01',
            ],
        ])),
    ]);

    $result = $driver->verify('RRR123');

    expect($result->status)->toBe('pending');
});

test('remita verify throws exception when statusCode is not 00 or 01', function () {
    $driver = createRemitaDriverWithMock([
        new Response(200, [], json_encode([
            'statusCode' => '02',
            'statusMessage' => 'Failed',
        ])),
    ]);

    $driver->verify('RRR123');
})->throws(VerificationException::class);

test('remita verify handles network error', function () {
    $mock = new MockHandler([
        new ConnectException('Timeout', new Request('GET', '/remita/exapp/api/v1/send/api/echannelsvc/merchant/api/paymentstatus')),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $driver = new RemitaDriver([
        'public_key' => 'test_public',
        'secret_key' => 'test_secret',
        'currencies' => ['NGN'],
    ]);
    $driver->setClient($client);

    $driver->verify('RRR123');
})->throws(VerificationException::class);
