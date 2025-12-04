# Webhooks Guide

Comprehensive guide to handling payment webhooks.

## Overview

Webhooks allow payment providers to notify your application about payment events in real-time.

## Automatic Setup

The package automatically registers webhook routes for all providers:

```
POST /payments/webhook/paystack
POST /payments/webhook/flutterwave
POST /payments/webhook/monnify
POST /payments/webhook/stripe
POST /payments/webhook/paypal
```

## Configuration

### Enable/Disable Signature Verification

```php
// config/payments.php
'webhook' => [
    'verify_signature' => true, // Recommended for production
    'middleware' => ['api'],
    'path' => '/payments/webhook',
],
```

### Provider Dashboard Setup

Configure webhook URLs in each provider's dashboard:

**Paystack**: Settings → Webhook → `https://yourdomain.com/payments/webhook/paystack`

**Flutterwave**: Settings → Webhooks → `https://yourdomain.com/payments/webhook/flutterwave`

**Monnify**: Settings → Webhooks → `https://yourdomain.com/payments/webhook/monnify`

**Stripe**: Developers → Webhooks → `https://yourdomain.com/payments/webhook/stripe`

**PayPal**: Developer Dashboard → Webhooks → `https://yourdomain.com/payments/webhook/paypal`

## Handling Webhooks

### Using Event Listeners

```php
// app/Providers/EventServiceProvider.php

protected $listen = [
    'payments.webhook.paystack' => [
        \App\Listeners\HandlePaystackWebhook::class,
    ],
    'payments.webhook.flutterwave' => [
        \App\Listeners\HandleFlutterwaveWebhook::class,
    ],
    'payments.webhook' => [
        \App\Listeners\HandleAnyWebhook::class,
    ],
];
```

### Listener Example

```php
// app/Listeners/HandlePaystackWebhook.php

namespace App\Listeners;

class HandlePaystackWebhook
{
    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? null;
        
        match($event) {
            'charge.success' => $this->handleSuccessfulCharge($payload['data']),
            'charge.failed' => $this->handleFailedCharge($payload['data']),
            'transfer.success' => $this->handleSuccessfulTransfer($payload['data']),
            default => logger()->info("Unhandled Paystack webhook: {$event}"),
        };
    }
    
    private function handleSuccessfulCharge(array $data): void
    {
        $reference = $data['reference'];
        
        // Find order in database
        $order = Order::where('payment_reference', $reference)->first();
        
        if ($order && $order->status === 'pending') {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            
            // Send confirmation email
            Mail::to($order->customer_email)->send(new OrderConfirmation($order));
            
            // Dispatch other jobs
            ProcessOrder::dispatch($order);
        }
    }
    
    private function handleFailedCharge(array $data): void
    {
        $reference = $data['reference'];
        
        $order = Order::where('payment_reference', $reference)->first();
        
        if ($order) {
            $order->update(['status' => 'failed']);
            
            Mail::to($order->customer_email)->send(new PaymentFailed($order));
        }
    }
}
```

### Generic Webhook Handler

```php
// app/Listeners/HandleAnyWebhook.php

namespace App\Listeners;

use KenDeNigerian\PayZephyr\Facades\Payment;

class HandleAnyWebhook
{
    public function handle(string $provider, array $payload): void
    {
        // Extract reference (varies by provider)
        $reference = $this->extractReference($provider, $payload);
        
        if (!$reference) {
            logger()->warning("No reference found in webhook", [
                'provider' => $provider,
            ]);
            return;
        }
        
        try {
            // Verify the payment
            $verification = Payment::verify($reference, $provider);
            
            if ($verification->isSuccessful()) {
                $this->updateOrder($reference, $verification);
            }
        } catch (\Exception $e) {
            logger()->error("Webhook processing failed", [
                'provider' => $provider,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    private function extractReference(string $provider, array $payload): ?string
    {
        return match($provider) {
            'paystack' => $payload['data']['reference'] ?? null,
            'flutterwave' => $payload['data']['tx_ref'] ?? null,
            'monnify' => $payload['paymentReference'] ?? null,
            'stripe' => $payload['data']['object']['metadata']['reference'] ?? null,
            'paypal' => $payload['resource']['custom_id'] ?? null,
            default => null,
        };
    }
    
    private function updateOrder(string $reference, $verification): void
    {
        $order = Order::where('payment_reference', $reference)->first();
        
        if ($order) {
            $order->update([
                'status' => 'paid',
                'amount_paid' => $verification->amount,
                'paid_at' => $verification->paidAt,
                'payment_channel' => $verification->channel,
                'payment_provider' => $verification->provider,
            ]);
        }
    }
}
```

## Webhook Payloads

### Paystack

```json
{
  "event": "charge.success",
  "data": {
    "reference": "ref_123",
    "amount": 50000,
    "currency": "NGN",
    "status": "success",
    "customer": {
      "email": "customer@example.com"
    }
  }
}
```

### Flutterwave

```json
{
  "event": "charge.completed",
  "data": {
    "id": 123456,
    "tx_ref": "FLW_ref_123",
    "amount": 100.00,
    "currency": "NGN",
    "status": "successful"
  }
}
```

### Monnify

```json
{
  "eventType": "SUCCESSFUL_TRANSACTION",
  "paymentReference": "MON_ref_123",
  "amountPaid": "25000.00",
  "paymentStatus": "PAID"
}
```

### Stripe

```json
{
  "type": "payment_intent.succeeded",
  "data": {
    "object": {
      "id": "pi_xxx",
      "amount": 10000,
      "currency": "usd",
      "status": "succeeded",
      "metadata": {
        "reference": "ORDER_123"
      }
    }
  }
}
```

### PayPal

```json
{
  "event_type": "PAYMENT.CAPTURE.COMPLETED",
  "resource": {
    "id": "xxx",
    "custom_id": "PAYPAL_ref_123",
    "amount": {
      "value": "100.00",
      "currency_code": "USD"
    },
    "status": "COMPLETED"
  }
}
```

## Security Best Practices

### 1. Always Verify Signatures

Never disable signature verification in production:

```php
'webhook' => [
    'verify_signature' => true, // Always true in production!
],
```

### 2. Verify Payment Status

Always re-verify payment status:

```php
$verification = Payment::verify($reference);

if ($verification->isSuccessful()) {
    // Process order
}
```

### 3. Idempotency

Handle duplicate webhooks:

```php
$order = Order::where('payment_reference', $reference)->first();

if ($order->status === 'paid') {
    logger()->info("Order already processed", ['reference' => $reference]);
    return;
}

// Process order...
```

### 4. Logging

Log all webhook activity:

```php
logger()->info("Webhook received", [
    'provider' => $provider,
    'event' => $payload['event'] ?? 'unknown',
    'reference' => $reference,
]);
```

### 5. Error Handling

Handle errors gracefully:

```php
try {
    // Process webhook
} catch (\Exception $e) {
    logger()->error("Webhook processing failed", [
        'error' => $e->getMessage(),
        'provider' => $provider,
    ]);
    
    // Don't throw - return 200 to prevent retries
    return response()->json(['status' => 'received'], 200);
}
```

## Testing Webhooks Locally

### Using ngrok

```bash
# Start ngrok
ngrok http 8000

# Use the HTTPS URL in provider dashboards
https://abc123.ngrok.io/payments/webhook/paystack
```

### Manual Testing

```bash
curl -X POST http://localhost:8000/payments/webhook/paystack \
  -H "Content-Type: application/json" \
  -H "x-paystack-signature: SIGNATURE_HERE" \
  -d '{"event":"charge.success","data":{"reference":"test_ref"}}'
```

## Troubleshooting

### Webhook Not Received

1. Check provider dashboard for delivery status
2. Verify webhook URL is correct and accessible
3. Check server logs
4. Ensure HTTPS (some providers require it)
5. Check firewall/security settings

### Signature Validation Fails

1. Verify webhook secret in `.env`
2. Check provider documentation for header name
3. Ensure raw body is used (not parsed JSON)
4. Check for whitespace in secret key

### Duplicate Processing

1. Implement idempotency checks
2. Use database transactions
3. Check order status before processing

## Webhook Events Reference

### Paystack Events
- charge.success
- charge.failed
- transfer.success
- transfer.failed
- subscription.create
- subscription.disable

### Flutterwave Events
- charge.completed
- transfer.completed

### Monnify Events
- SUCCESSFUL_TRANSACTION
- FAILED_TRANSACTION
- OVERPAID_TRANSACTION

### Stripe Events
- payment_intent.succeeded
- payment_intent.payment_failed
- charge.succeeded
- charge.failed

### PayPal Events
- PAYMENT.CAPTURE.COMPLETED
- PAYMENT.CAPTURE.DENIED
- CHECKOUT.ORDER.APPROVED
