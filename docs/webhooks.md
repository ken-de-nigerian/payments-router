# Webhooks

Webhooks are notifications from payment providers sent to your server when payments complete.

## Webhook URLs

Routes are automatically created:
- `/payments/webhook/paystack`
- `/payments/webhook/flutterwave`
- `/payments/webhook/monnify`
- `/payments/webhook/stripe`
- `/payments/webhook/paypal`

Configure these URLs in your provider dashboards.

## Configuration

```php
// config/payments.php
'webhook' => [
    'verify_signature' => true,  // Always true in production
    'path' => '/payments/webhook',
],
```

## Listening to Events

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    'payments.webhook.paystack' => [
        \App\Listeners\HandlePaystackWebhook::class,
    ],
    'payments.webhook' => [
        \App\Listeners\HandleAnyWebhook::class,
    ],
];
```

## Example Listener

```php
namespace App\Listeners;

class HandlePaystackWebhook
{
    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? null;
        
        match($event) {
            'charge.success' => $this->handleSuccess($payload['data']),
            'charge.failed' => $this->handleFailure($payload['data']),
            default => logger()->info("Unhandled event: {$event}"),
        };
    }
    
    private function handleSuccess(array $data): void
    {
        $reference = $data['reference'];
        $order = Order::where('payment_reference', $reference)->first();
        
        if ($order && $order->status !== 'paid') {
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            Mail::to($order->customer_email)->send(new OrderConfirmation($order));
        }
    }
}
```

## Webhook Events

**Paystack:**
- `charge.success`, `charge.failed`

**Flutterwave:**
- `charge.completed`

**Monnify:**
- `SUCCESSFUL_TRANSACTION`, `FAILED_TRANSACTION`

**Stripe:**
- `payment_intent.succeeded`, `payment_intent.payment_failed`

**PayPal:**
- `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`

## Security

1. Always verify signatures (keep `verify_signature => true`)
2. Re-verify payment status with `Payment::verify()`
3. Handle duplicate webhooks (check order status before processing)
4. Use HTTPS for webhook URLs
5. Log all webhook activity

## Testing Locally

Use ngrok:

```bash
php artisan serve
ngrok http 8000
```

Use the ngrok URL in provider dashboard: `https://abc123.ngrok.io/payments/webhook/paystack`

## Troubleshooting

**Webhook not received:**
- Check provider dashboard for delivery status
- Verify URL is correct and accessible
- Ensure HTTPS is used

**Signature validation fails:**
- Verify webhook secret in `.env`
- Check `verify_signature => true` in config
- Ensure raw body is used (not parsed JSON)

**Duplicate webhooks:**
- Check order status before processing
- Use database unique constraints
- Implement idempotency checks
