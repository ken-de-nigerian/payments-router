<?php

use KenDeNigerian\PaymentsRouter\DataObjects\VerificationResponse;

test('verification response checks success status', function () {
    $response = VerificationResponse::fromArray([
        'reference' => 'ref_123',
        'status' => 'success',
        'amount' => 1000,
        'currency' => 'NGN',
    ]);
    
    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isFailed())->toBeFalse()
        ->and($response->isPending())->toBeFalse();
});

test('verification response checks failed status', function () {
    $response = VerificationResponse::fromArray([
        'reference' => 'ref_123',
        'status' => 'failed',
        'amount' => 1000,
        'currency' => 'NGN',
    ]);
    
    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isFailed())->toBeTrue();
});

test('verification response checks pending status', function () {
    $response = VerificationResponse::fromArray([
        'reference' => 'ref_123',
        'status' => 'pending',
        'amount' => 1000,
        'currency' => 'NGN',
    ]);
    
    expect($response->isPending())->toBeTrue()
        ->and($response->isSuccessful())->toBeFalse();
});
