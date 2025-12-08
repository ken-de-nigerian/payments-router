# PayZephyr

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kendenigerian/payzephyr.svg?style=flat-square)](https://packagist.org/packages/kendenigerian/payzephyr)
[![Total Downloads](https://img.shields.io/packagist/dt/kendenigerian/payzephyr.svg?style=flat-square)](https://packagist.org/packages/kendenigerian/payzephyr)
[![Tests](https://github.com/ken-de-nigerian/payzephyr/actions/workflows/tests.yml/badge.svg)](https://github.com/ken-de-nigerian/payzephyr/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Unified payment abstraction for Laravel. Supports Paystack, Flutterwave, Monnify, Stripe, and PayPal with automatic fallback.

## Features

- Multiple providers: Paystack, Flutterwave, Monnify, Stripe, PayPal
- Automatic fallback between providers
- Fluent API
- Webhook security with signature validation
- Transaction logging
- Multi-currency support
- Health checks

## Installation

```bash
composer require kendenigerian/payzephyr
php artisan vendor:publish --tag=payments-config
php artisan vendor:publish --tag=payments-migrations
php artisan migrate
```

## Configuration

Add to `.env`:

```env
PAYMENTS_DEFAULT_PROVIDER=paystack
PAYSTACK_SECRET_KEY=sk_test_xxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxx
PAYSTACK_ENABLED=true
```

See [docs/DOCUMENTATION.md](docs/DOCUMENTATION.md) for all providers.

## Quick Start

### Basic Payment

```php
use KenDeNigerian\PayZephyr\Facades\Payment;

return Payment::amount(10000)
    ->email('customer@example.com')
    ->callback(route('payment.callback'))
    ->redirect();
```

### Verify Payment

```php
public function callback(Request $request)
{
    $verification = Payment::verify($request->input('reference'));
    
    if ($verification->isSuccessful()) {
        // Payment successful
    }
}
```

### With Options

```php
return Payment::amount(50000)
    ->currency('NGN')
    ->email('customer@example.com')
    ->reference('ORDER_' . time())
    ->metadata(['order_id' => 12345])
    ->callback(route('payment.callback'))
    ->redirect();
```

### Multiple Providers

```php
return Payment::amount(10000)
    ->email('customer@example.com')
    ->with(['paystack', 'stripe'])
    ->callback(route('payment.callback'))
    ->redirect();
```

## Supported Providers

| Provider | Currencies | Features |
|----------|-----------|----------|
| Paystack | NGN, GHS, ZAR, USD | USSD, Bank Transfer |
| Flutterwave | NGN, USD, EUR, GBP, KES, UGX, TZS | Mobile Money, MPESA |
| Monnify | NGN | Bank Transfer, Dynamic Accounts |
| Stripe | 135+ currencies | Apple Pay, Google Pay, SCA |
| PayPal | USD, EUR, GBP, CAD, AUD | PayPal Balance, Credit |

## Webhooks

Webhook URLs:
- Paystack: `/payments/webhook/paystack`
- Flutterwave: `/payments/webhook/flutterwave`
- Monnify: `/payments/webhook/monnify`
- Stripe: `/payments/webhook/stripe`
- PayPal: `/payments/webhook/paypal`

Listen to events:

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    'payments.webhook.paystack' => [
        \App\Listeners\HandlePaystackWebhook::class,
    ],
];
```

See [docs/webhooks.md](docs/webhooks.md) for details.

## Transaction Logging

```php
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;

PaymentTransaction::successful()->get();
PaymentTransaction::where('reference', 'ORDER_123')->first();
```

## API Reference

### Builder Methods (Chainable)

```php
Payment::amount(float $amount)
Payment::currency(string $currency)
Payment::email(string $email)
Payment::reference(string $reference)
Payment::callback(string $url)  // Required
Payment::metadata(array $metadata)
Payment::with(string|array $providers)
```

### Action Methods

```php
Payment::charge()      // Returns ChargeResponseDTO
Payment::redirect()    // Redirects to payment page
Payment::verify(string $reference)  // Standalone, not chainable
```

## Documentation

- [Getting Started](docs/GETTING_STARTED.md) - Beginner tutorial
- [Complete Documentation](docs/DOCUMENTATION.md) - Full guide
- [Provider Details](docs/providers.md) - Provider-specific info
- [Webhooks](docs/webhooks.md) - Webhook guide
- [Architecture](docs/architecture.md) - System design

## Testing

```bash
composer test
composer test-coverage
```

## Security

Report vulnerabilities to: **ken.de.nigerian@gmail.com**

## License

MIT License. See [LICENSE](LICENSE).

## Support

- 📧 Email: ken.de.nigerian@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/ken-de-nigerian/payzephyr/issues)
- 📖 Docs: [docs/](docs/INDEX.md)
