<?php
return [
    'default' => env('PAYMENTS_DEFAULT', 'paystack'),
    'fallback' => env('PAYMENTS_FALLBACK', 'stripe'),
    'providers' => [
        'paystack' => [
            'driver' => \Nwaneri\PaymentsRouter\Drivers\PaystackDriver::class,
            'secret' => env('PAYSTACK_SECRET'),
            'public' => env('PAYSTACK_PUBLIC'),
            'base_url' => env('PAYSTACK_BASE', 'https://api.paystack.co'),
            'currencies' => ['NGN', 'USD'],
        ],
        'stripe' => [
            'driver' => \Nwaneri\PaymentsRouter\Drivers\StripeDriver::class,
            'secret' => env('STRIPE_SECRET'),
            'public' => env('STRIPE_PUBLIC'),
            'base_url' => env('STRIPE_BASE', 'https://api.stripe.com'),
            'currencies' => ['USD', 'EUR'],
        ],
    ],
    'currency' => [
        'driver' => 'coingecko',
        'cache_ttl' => 3600,
    ],
    'webhook' => [
        'path' => env('PAYMENTS_WEBHOOK_PATH', '/payments/webhook'),
        'verify_signature' => true,
    ],
    'health_check' => [
        'enabled' => true,
        'interval' => 300,
    ],
];
