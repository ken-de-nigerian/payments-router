<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;
use KenDeNigerian\PayZephyr\Http\Resources\ChargeResource;
use KenDeNigerian\PayZephyr\Http\Resources\VerificationResource;

test('charge resource transforms dto correctly', function () {
    $dto = new ChargeResponseDTO(
        reference: 'ref_123',
        authorizationUrl: 'https://paystack.com/checkout/ref_123',
        accessCode: 'access_123',
        status: 'pending',
        metadata: [
            'amount' => 10000,
            'currency' => 'NGN',
        ]
    );

    $resource = new ChargeResource($dto);
    $array = $resource->toArray(new Request);

    expect($array)->toHaveKeys(['reference', 'authorization_url', 'status', 'amount', 'metadata', 'created_at'])
        ->and($array['reference'])->toBe('ref_123')
        ->and($array['authorization_url'])->toBe('https://paystack.com/checkout/ref_123')
        ->and($array['status'])->toBe('pending')
        ->and($array['amount'])->toBeArray()
        ->and($array['amount']['value'])->toBe(10000)
        ->and($array['amount']['currency'])->toBe('NGN');
});

test('verification resource transforms dto correctly', function () {
    $dto = new VerificationResponseDTO(
        reference: 'ref_123',
        status: 'success',
        amount: 10000.0,
        currency: 'NGN',
        channel: 'card',
        metadata: [
            'amount' => 10000,
            'currency' => 'NGN',
        ],
        paidAt: now()->toIso8601String()
    );

    $resource = new VerificationResource($dto);
    $array = $resource->toArray(new Request);

    expect($array)->toHaveKeys(['reference', 'status', 'channel', 'amount', 'paid_at', 'metadata', 'verified_at'])
        ->and($array['reference'])->toBe('ref_123')
        ->and($array['status'])->toBe('success')
        ->and($array['channel'])->toBe('card')
        ->and($array['paid_at'])->not->toBeNull()
        ->and($array['verified_at'])->not->toBeNull();
});

test('verification resource handles null paid_at', function () {
    $dto = new VerificationResponseDTO(
        reference: 'ref_123',
        status: 'pending',
        amount: 0.0,
        currency: 'NGN',
        channel: 'card',
        metadata: [],
        paidAt: null
    );

    $resource = new VerificationResource($dto);
    $array = $resource->toArray(new Request);

    expect($array['paid_at'])->toBeNull();
});

test('charge resource includes provider when available', function () {
    $dto = new ChargeResponseDTO(
        reference: 'ref_123',
        authorizationUrl: 'https://paystack.com/checkout/ref_123',
        accessCode: 'access_123',
        status: 'pending',
        provider: 'paystack',
        metadata: []
    );

    $resource = new ChargeResource($dto);
    $array = $resource->toArray(new Request);

    expect($array['provider'])->toBe('paystack');
});

test('charge resource handles missing amount in metadata', function () {
    $dto = new ChargeResponseDTO(
        reference: 'ref_123',
        authorizationUrl: 'https://paystack.com/checkout/ref_123',
        accessCode: 'access_123',
        status: 'pending',
        metadata: []
    );

    $resource = new ChargeResource($dto);
    $array = $resource->toArray(new Request);

    expect($array['amount']['value'])->toBeNull()
        ->and($array['amount']['currency'])->toBeNull();
});
