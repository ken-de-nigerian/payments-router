<?php

use KenDeNigerian\PayZephyr\Drivers\PaystackDriver;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;

test(/**
 * @throws InvalidConfigurationException
 */ 'paystack driver initializes correctly', function () {
    $config = [
        'secret_key' => 'sk_test_xxx',
        'public_key' => 'pk_test_xxx',
        'enabled' => true,
        'currencies' => ['NGN', 'USD'],
    ];

    $driver = new PaystackDriver($config);

    expect($driver->getName())->toBe('paystack')
        ->and($driver->getSupportedCurrencies())->toBe(['NGN', 'USD']);
});

test(/**
 * @throws InvalidConfigurationException
 */ 'paystack driver validates webhook signature', function () {
    $config = ['secret_key' => 'test_secret'];
    $driver = new PaystackDriver($config);

    $body = '{"event":"charge.success"}';
    $signature = hash_hmac('sha512', $body, 'test_secret');

    $headers = ['x-paystack-signature' => [$signature]];

    expect($driver->validateWebhook($headers, $body))->toBeTrue();
});
