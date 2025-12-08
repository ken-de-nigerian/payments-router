# PayZephyr Documentation

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

See [providers.md](providers.md) for all providers.

### Callback URL

**Required:** Use `->callback()` in payment chain:

```php
Payment::amount(10000)
    ->email('customer@example.com')
    ->callback(route('payment.callback'))  // Required
    ->redirect();
```

## Basic Usage

### Simple Payment

```php
use KenDeNigerian\PayZephyr\Facades\Payment;

return Payment::amount(10000)
    ->email('customer@example.com')
    ->callback(route('payment.callback'))
    ->redirect();
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

## Advanced Usage

### Multiple Providers

```php
return Payment::amount(10000)
    ->email('customer@example.com')
    ->with(['paystack', 'stripe'])
    ->callback(route('payment.callback'))
    ->redirect();
```

### API-Only Mode

```php
$response = Payment::amount(10000)
    ->email('customer@example.com')
    ->callback(route('payment.callback'))
    ->charge();

return response()->json([
    'reference' => $response->reference,
    'authorization_url' => $response->authorizationUrl,
]);
```

### Direct Driver Access

```php
$manager = app(PaymentManager::class);
$driver = $manager->driver('paystack');

if ($driver->healthCheck()) {
    // Provider available
}
```

## Payment Channels

Unified channel names work across all providers:

- `'card'` - Credit/debit cards
- `'bank_transfer'` - Bank transfers
- `'ussd'` - USSD payments
- `'mobile_money'` - Mobile money
- `'qr_code'` - QR code payments

```php
Payment::amount(10000)
    ->email('customer@example.com')
    ->channels(['card', 'bank_transfer'])
    ->callback(route('payment.callback'))
    ->redirect();
```

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

See [webhooks.md](webhooks.md) for details.

## Transaction Logging

```php
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;

PaymentTransaction::successful()->get();
PaymentTransaction::where('reference', 'ORDER_123')->first();

if ($transaction->isSuccessful()) {
    // Process payment
}
```

## Error Handling

```php
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\ProviderException;

try {
    return Payment::amount(10000)
        ->email('customer@example.com')
        ->callback(route('payment.callback'))
        ->redirect();
} catch (ChargeException $e) {
    // Payment initialization failed
} catch (ProviderException $e) {
    // All providers failed
}
```

### Exception Types

- `ChargeException` - Payment initialization failed
- `VerificationException` - Payment verification failed
- `ProviderException` - All providers failed
- `DriverNotFoundException` - Provider not found
- `InvalidConfigurationException` - Configuration error

## API Reference

### Builder Methods (Chainable)

```php
Payment::amount(float $amount)
Payment::currency(string $currency)
Payment::email(string $email)
Payment::reference(string $reference)
Payment::callback(string $url)  // Required
Payment::metadata(array $metadata)
Payment::description(string $description)
Payment::customer(array $customer)
Payment::channels(array $channels)
Payment::with(string|array $providers)
Payment::using(string|array $providers)  // Alias for with()
```

### Action Methods

```php
Payment::charge()  // Returns ChargeResponseDTO
Payment::redirect()  // Redirects to payment page
Payment::verify(string $reference, ?string $provider = null)  // Standalone
```

### Response Objects

**ChargeResponseDTO:**
- `reference`, `authorizationUrl`, `accessCode`, `status`, `metadata`, `provider`
- `isSuccessful()`, `isPending()`

**VerificationResponseDTO:**
- `reference`, `status`, `amount`, `currency`, `paidAt`, `channel`, `customer`
- `isSuccessful()`, `isFailed()`, `isPending()`

## Troubleshooting

### Payment Initialization Fails
1. Check provider credentials in `.env`
2. Verify provider is enabled
3. Check currency support
4. Review error logs

### Webhook Not Received
1. Verify webhook URL is correct
2. Check signature verification
3. Ensure endpoint is accessible
4. Check provider dashboard

### Verification Fails
1. Ensure reference is correct
2. Check provider supports verification
3. Verify transaction exists
4. Review error logs

## Security

1. Always use HTTPS for webhooks
2. Enable signature verification in production
3. Rotate API keys periodically
4. Use environment variables for credentials
5. Monitor failed webhooks

See [SECURITY_AUDIT.md](SECURITY_AUDIT.md) for details.

## Testing

```bash
composer test
composer test-coverage
```

```php
test('payment charge works', function () {
    $response = Payment::amount(10000)
        ->email('test@example.com')
        ->callback(route('payment.callback'))
        ->charge();

    expect($response->reference)->toBeString();
});
```

## Support

- 📧 Email: ken.de.nigerian@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/ken-de-nigerian/payzephyr/issues)
- 📖 Docs: [docs/](INDEX.md)
