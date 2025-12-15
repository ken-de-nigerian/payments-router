# Getting Started

**What is PayZephyr?** A Laravel package that lets you accept payments from multiple providers (Paystack, Stripe, PayPal, etc.) using one simple API. No need to write different code for each provider.

**This guide will help you:**
1. Install PayZephyr
2. Configure your first payment provider
3. Create a working payment page
4. Test it with a test card

**Time needed:** ~10 minutes

## Prerequisites

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- Composer
- Payment provider account (Paystack, Stripe, etc.)

---

## Installation

```bash
composer require kendenigerian/payzephyr
php artisan payzephyr:install
```

Publishes config, migrations, and optionally runs migrations.

**Troubleshooting:** If you get an error, make sure:
- Your database is configured in `.env`
- Database connection is working (`php artisan migrate:status`)

**Alternative Manual Setup:**
> ```bash
> php artisan vendor:publish --tag=payments-config
> php artisan vendor:publish --tag=payments-migrations
> php artisan migrate
> ```

---

## Configuration

1. Get credentials from your provider dashboard (Paystack: Settings → API Keys)
2. Add to `.env`:

```env
PAYMENTS_DEFAULT_PROVIDER=paystack
PAYSTACK_SECRET_KEY=sk_test_xxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxx
PAYSTACK_ENABLED=true
```

3. Clear config cache: `php artisan config:clear`

---

## Your First Payment

Let's create a simple payment page from scratch!

**How it works:**
1. Customer visits `/payment` page
2. Customer enters email and clicks "Pay"
3. Customer is redirected to Paystack's secure payment page
4. Customer completes payment
5. Customer returns to `/payment/callback`
6. You verify the payment and show success/failure

### Routes

```php
// routes/web.php
use KenDeNigerian\PayZephyr\Facades\Payment;

// Step 1: Show payment form
Route::get('/payment', function () {
    return view('payment');
});

// Step 2: Process payment (redirects to payment provider)
Route::post('/payment/process', function () {
    // Amount: 100.00 = ₦100.00 (package handles conversion to minor units automatically)
    return Payment::amount(100.00)
        ->email(request()->email)
        ->callback(route('payment.callback')) // Where to return after payment
        ->redirect(); // Redirects customer to payment page
});

// Step 3: Handle payment result (after customer returns)
Route::get('/payment/callback', function () {
    // Verify payment using reference from URL
    $verification = Payment::verify(request()->input('reference'));
    
    if ($verification->isSuccessful()) {
        return redirect('/payment')->with('success', 'Payment successful!');
    }
    
    return redirect('/payment')->with('error', 'Payment failed');
});
```

### View

```blade
{{-- resources/views/payment.blade.php --}}
<h1>Test Payment</h1>

@if(session('success'))
    <div>{{ session('success') }}</div>
@endif

@if(session('error'))
    <div>{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('payment.process') }}">
    @csrf
    <input type="email" name="email" required placeholder="customer@example.com">
    <button type="submit">Pay ₦100.00</button>
</form>
```

### Test

1. Start server: `php artisan serve`
2. Visit: `http://localhost:8000/payment`
3. Enter any email and click "Pay ₦100.00"
4. You'll be redirected to Paystack's test payment page
5. Use test card: `4084084084084081`
   - Expiry: Any future date (e.g., 12/25)
   - CVV: Any 3 digits (e.g., 123)
   - PIN: Any 4 digits (e.g., 0000)
6. Complete payment
7. You'll be redirected back to see "Payment successful!"

---

## Common Issues

**"Driver not found" error:**
- Check `PAYSTACK_ENABLED=true` in `.env`
- Run `php artisan config:clear`
- Make sure you saved the `.env` file

**"Invalid credentials" error:**
- Verify key starts with `sk_test_` (for test keys)
- Check for extra spaces before/after the key
- Don't use quotes around the key in `.env`
- Run `php artisan config:clear` after fixing

**"Table not found" error:**
- Run `php artisan migrate`
- Check database connection in `.env`

**Payment page not loading:**
- Check logs: `storage/logs/laravel.log`
- Make sure you're using test keys (not live keys)
- Verify provider is enabled in config

---

## Next Steps

**You've made your first payment!** Here's what to do next:

1. **Set up webhooks** (recommended for production)
   - Webhooks are more reliable than callbacks
   - They notify your app even if customer doesn't return
   - See [Webhook Guide](webhooks.md#queue-workers-required)

2. **Read full documentation**
   - [Complete Guide](DOCUMENTATION.md) - All features and options
   - [Webhooks](webhooks.md) - Set up webhook handling
   - [Providers](providers.md) - Add more payment providers

3. **Production checklist**
   - Switch to live API keys (from test keys)
   - Enable webhook signature verification
   - Set up queue workers (Supervisor or Systemd)
   - Test with small amounts first

---

## Tips

- Use test keys first
- Check `storage/logs/laravel.log` for errors
- Start with one provider before adding more

## Need Help?

- [GitHub Issues](https://github.com/ken-de-nigerian/payzephyr/issues)
- [Documentation](DOCUMENTATION.md)

Happy coding!
