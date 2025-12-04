# payments-router

Unified payment router for multiple payment providers with fallback, webhooks and currency conversion.

## Features
- Fluent static API (Payment facade)
- Drivers for Paystack and Stripe (examples)
- Fallback provider support
- Webhook route + signature verification
- Health checks and currency helper

## Installation
```bash
composer require nwaneri/payments-router
php artisan vendor:publish --tag=config
```

## Usage
```php
return \Payment::amount(200000)
    ->with('paystack')
    ->email('buyer@example.com')
    ->callback(route('payments.callback'))
    ->redirect();
```

## Implemented drivers
- Paystack (HTTP via Guzzle)
- Stripe (stripe-php)

## Tests
```bash
composer install --dev
vendor/bin/phpunit
```
