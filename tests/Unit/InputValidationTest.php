<?php

declare(strict_types=1);

use InvalidArgumentException;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;

test('it rejects emails with double dots', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'user..name@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid email address');
});

test('it rejects emails with trailing dots', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'user@example.com.',
    ]))->toThrow(InvalidArgumentException::class);
});

test('it rejects emails with leading dots', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => '.user@example.com',
    ]))->toThrow(InvalidArgumentException::class);
});

test('it rejects emails with dot after at symbol', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'user@.example.com',
    ]))->toThrow(InvalidArgumentException::class);
});

test('it rejects emails with local part exceeding 64 characters', function () {
    $longLocal = str_repeat('a', 65).'@example.com';

    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => $longLocal,
    ]))->toThrow(InvalidArgumentException::class);
});

test('it accepts valid email addresses', function () {
    $validEmails = [
        'user@example.com',
        'user.name@example.com',
        'user+tag@example.com',
        'user_name@example.co.uk',
        'user123@example-domain.com',
    ];

    foreach ($validEmails as $email) {
        $dto = ChargeRequestDTO::fromArray([
            'amount' => 10000,
            'currency' => 'NGN',
            'email' => $email,
        ]);

        expect($dto->email)->toBe($email);
    }
});

test('it rejects http callback urls in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'callback_url' => 'http://example.com/callback',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid callback URL');
});

test('it accepts https callback urls in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $dto = ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'callback_url' => 'https://example.com/callback',
    ]);

    expect($dto->callbackUrl)->toBe('https://example.com/callback');
});

test('it accepts http callback urls in non-production', function () {
    app()->detectEnvironment(fn () => 'local');

    $dto = ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'callback_url' => 'http://localhost:8000/callback',
    ]);

    expect($dto->callbackUrl)->toBe('http://localhost:8000/callback');
});

test('it rejects invalid urls', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'callback_url' => 'not-a-valid-url',
    ]))->toThrow(InvalidArgumentException::class);
});

test('it rejects references with special characters', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'reference' => 'ORDER_123; DROP TABLE users--',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid reference format');
});

test('it rejects references with spaces', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'reference' => 'ORDER 123',
    ]))->toThrow(InvalidArgumentException::class);
});

test('it rejects references with at symbols', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'reference' => 'ORDER@123',
    ]))->toThrow(InvalidArgumentException::class);
});

test('it accepts valid reference formats', function () {
    $validReferences = [
        'ORDER_123',
        'ORDER-123-ABC',
        'ORDER123ABC',
        'order_123_abc',
        'ORDER_123-ABC_456',
    ];

    foreach ($validReferences as $reference) {
        $dto = ChargeRequestDTO::fromArray([
            'amount' => 10000,
            'currency' => 'NGN',
            'email' => 'test@example.com',
            'reference' => $reference,
        ]);

        expect($dto->reference)->toBe($reference);
    }
});

test('it rejects references exceeding 255 characters', function () {
    $longReference = str_repeat('A', 256);

    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
        'reference' => $longReference,
    ]))->toThrow(InvalidArgumentException::class);
});

test('it accepts valid international currencies', function () {
    $validCurrencies = ['NGN', 'USD', 'EUR', 'GBP', 'JPY', 'CNY', 'CAD', 'AUD'];

    foreach ($validCurrencies as $currency) {
        $dto = ChargeRequestDTO::fromArray([
            'amount' => 10000,
            'currency' => $currency,
            'email' => 'test@example.com',
        ]);

        expect($dto->currency)->toBe($currency);
    }
});

test('it rejects numeric currency codes', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => '566',
        'email' => 'test@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'Currency must contain only letters');
});

test('it rejects currency codes with wrong length', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 10000,
        'currency' => 'US',
        'email' => 'test@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'Currency must be a 3-letter ISO code');
});

test('it rejects negative amounts', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => -1000,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'Amount must be greater than zero');
});

test('it rejects zero amounts', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 0,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'Amount must be greater than zero');
});

test('it rejects amounts exceeding maximum', function () {
    expect(fn () => ChargeRequestDTO::fromArray([
        'amount' => 1000000000.00,
        'currency' => 'NGN',
        'email' => 'test@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'Amount exceeds maximum allowed value');
});
