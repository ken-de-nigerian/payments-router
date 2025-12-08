# Getting Started

## Prerequisites

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- Composer
- Payment provider account (Paystack recommended for beginners)

## Installation

```bash
composer require kendenigerian/payzephyr
php artisan vendor:publish --tag=payments-config
php artisan vendor:publish --tag=payments-migrations
php artisan migrate
```

## Configuration

### Get Provider Credentials

**Paystack:**
1. Sign up at [paystack.com](https://paystack.com)
2. Go to Settings → API Keys & Webhooks
3. Copy Test Secret Key (`sk_test_...`)
4. Copy Test Public Key (`pk_test_...`)

### Add to `.env`

```env
PAYMENTS_DEFAULT_PROVIDER=paystack
PAYSTACK_SECRET_KEY=sk_test_your_key_here
PAYSTACK_PUBLIC_KEY=pk_test_your_key_here
PAYSTACK_ENABLED=true
```

```bash
php artisan config:clear
```

## Your First Payment

### Routes

```php
// routes/web.php
use KenDeNigerian\PayZephyr\Facades\Payment;

Route::post('/payment/process', function () {
    return Payment::amount(10000)  // ₦100.00
        ->email(request()->email)
        ->callback(route('payment.callback'))
        ->redirect();
})->name('payment.process');

Route::get('/payment/callback', function () {
    $verification = Payment::verify(request()->input('reference'));
    
    if ($verification->isSuccessful()) {
        return redirect('/')->with('success', 'Payment successful!');
    }
    
    return redirect('/')->with('error', 'Payment failed');
})->name('payment.callback');
```

### Test

1. Start server: `php artisan serve`
2. Visit payment page
3. Use test card: `4084084084084081` (any future expiry, any CVV)
4. Complete payment

## Common Issues

**Driver not found:**
- Check `PAYSTACK_ENABLED=true` in `.env`
- Run `php artisan config:clear`

**Invalid credentials:**
- Verify key is complete (including `sk_test_` prefix)
- No quotes in `.env`
- Run `php artisan config:clear`

**Table not found:**
- Run `php artisan migrate`

## Next Steps

- [Complete Documentation](DOCUMENTATION.md)
- [Webhook Guide](webhooks.md)
- [Provider Details](providers.md)

## Support

- 📧 Email: ken.de.nigerian@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/ken-de-nigerian/payzephyr/issues)
