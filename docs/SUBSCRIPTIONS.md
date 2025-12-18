# Subscriptions Guide

Complete guide to using subscription functionality in PayZephyr. Subscriptions follow the same unified fluent builder pattern as payments for consistency.

## ⚠️ Important: Current Provider Support

**Currently, only PaystackDriver supports subscriptions.** Support for other providers (Stripe, PayPal, etc.) will be added in future releases.

If you're a developer and want to add subscription support for a new driver, see the [Developer Guide](#developer-guide-adding-subscription-support-to-a-driver) section below.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Provider Support](#provider-support)
3. [Creating Subscription Plans](#creating-subscription-plans)
4. [Creating Subscriptions](#creating-subscriptions)
5. [Managing Subscriptions](#managing-subscriptions)
6. [Plan Management](#plan-management)
7. [Complete Workflow Examples](#complete-workflow-examples)
8. [Error Handling](#error-handling)
9. [Best Practices](#best-practices)
10. [Security Considerations](#security-considerations)
11. [Developer Guide: Adding Subscription Support to a Driver](#developer-guide-adding-subscription-support-to-a-driver)

---

## Getting Started

Subscriptions are accessed through the `Payment` facade or the `payment()` helper function, following the same pattern as regular payments:

```php
use KenDeNigerian\PayZephyr\Facades\Payment;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionPlanDTO;
```

### Basic Pattern

**Using the Facade:**
```php
// The unified pattern - same as Payment!
Payment::subscription()
    ->customer('user@example.com')
    ->plan('PLN_abc123')
    ->with('paystack')  // Currently only PaystackDriver supports subscriptions
    ->subscribe();      // Final action method (create() is also available as an alias)
```

**Using the Helper Function:**
```php
// The payment() helper works exactly like the Payment facade
payment()->subscription()
    ->customer('user@example.com')
    ->plan('PLN_abc123')
    ->with('paystack')  // Currently only PaystackDriver supports subscriptions
    ->subscribe();      // Final action method (create() is also available as an alias)
```

Both approaches work identically - use whichever you prefer!

---

## Provider Support

### Current Status

| Provider | Subscription Support | Status |
|----------|---------------------|--------|
| Paystack | ✅ Full Support | Available Now |
| Stripe | ❌ Not Yet | Coming Soon |
| PayPal | ❌ Not Yet | Coming Soon |
| Flutterwave | ❌ Not Yet | Coming Soon |
| Monnify | ❌ Not Yet | Coming Soon |
| Other Providers | ❌ Not Yet | Coming Soon |

### Why Only Paystack?

Subscription support requires provider-specific implementation. PaystackDriver was chosen as the first implementation because:

1. **Wide Adoption**: Paystack is widely used in the target markets
2. **Comprehensive API**: Paystack provides a complete subscription API
3. **Testing**: Extensive testing ensures reliability

### Future Support

We're actively working on adding subscription support for other providers. If you need subscription support for a specific provider, please:

1. Check our [GitHub Issues](https://github.com/ken-de-nigerian/payzephyr/issues) for planned support
2. Open a feature request if not already planned
3. Consider contributing (see [Developer Guide](#developer-guide-adding-subscription-support-to-a-driver))

---

## Creating Subscription Plans

### Basic Plan Creation

```php
use KenDeNigerian\PayZephyr\Facades\Payment;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionPlanDTO;

// Create a monthly subscription plan
$planDTO = new SubscriptionPlanDTO(
    name: 'Monthly Premium',
    amount: 5000.00,        // ₦5,000.00 (package handles conversion)
    interval: 'monthly',     // daily, weekly, monthly, annually
    currency: 'NGN',
    description: 'Monthly premium subscription with full access',
    sendInvoices: true,      // Send invoices to customers
    sendSms: true,           // Send SMS notifications
    metadata: [
        'plan_type' => 'premium',
        'features' => 'all',
    ]
);

// Using the facade
$plan = Payment::subscription()
    ->planData($planDTO)
    ->with('paystack')  // Required: Currently only PaystackDriver supports subscriptions
    ->createPlan();

// Or using the helper function
$plan = payment()->subscription()
    ->planData($planDTO)
    ->with('paystack')
    ->createPlan();

// Save the plan code to your database
$planCode = $plan['plan_code']; // e.g., 'PLN_abc123xyz'
```

### Plan with Invoice Limit

```php
$planDTO = new SubscriptionPlanDTO(
    name: 'Annual Plan',
    amount: 50000.00,        // ₦50,000.00
    interval: 'annually',
    currency: 'NGN',
    invoiceLimit: 12,       // Stop after 12 invoices
    description: 'Annual subscription plan'
);

$plan = Payment::subscription()
    ->planData($planDTO)
    ->with('paystack')
    ->createPlan();
```

### Plan with Different Intervals

```php
// Daily plan
$dailyPlan = new SubscriptionPlanDTO(
    name: 'Daily Access',
    amount: 100.00,
    interval: 'daily',
    currency: 'NGN'
);

// Weekly plan
$weeklyPlan = new SubscriptionPlanDTO(
    name: 'Weekly Access',
    amount: 500.00,
    interval: 'weekly',
    currency: 'NGN'
);

// Monthly plan
$monthlyPlan = new SubscriptionPlanDTO(
    name: 'Monthly Access',
    amount: 2000.00,
    interval: 'monthly',
    currency: 'NGN'
);

// Create all plans
$daily = Payment::subscription()->planData($dailyPlan)->with('paystack')->createPlan();
$weekly = Payment::subscription()->planData($weeklyPlan)->with('paystack')->createPlan();
$monthly = Payment::subscription()->planData($monthlyPlan)->with('paystack')->createPlan();
```

### Plan Validation

The `SubscriptionPlanDTO` automatically validates:
- **Name**: Must not be empty
- **Amount**: Must be greater than zero
- **Interval**: Must be one of: `daily`, `weekly`, `monthly`, `annually`
- **Currency**: Must be a valid 3-letter ISO code

Invalid plans will throw `InvalidArgumentException` before making any API calls.

---

## Creating Subscriptions

### Basic Subscription

```php
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->with('paystack')
    ->subscribe();

// Access subscription details
$subscriptionCode = $subscription->subscriptionCode;
$status = $subscription->status;
$emailToken = $subscription->emailToken;  // CRITICAL: Save this!

// Save to database IMMEDIATELY
DB::table('subscriptions')->insert([
    'user_id' => auth()->id(),
    'subscription_code' => $subscription->subscriptionCode,
    'email_token' => $subscription->emailToken,  // Required for cancel/enable
    'plan_code' => 'PLN_abc123',
    'status' => $subscription->status,
    'amount' => $subscription->amount,
    'next_payment_date' => $subscription->nextPaymentDate,
    'created_at' => now(),
]);
```

**Note**: Both `Payment::subscription()` and `payment()->subscription()` work identically. Always save the `emailToken` immediately - it's required for cancel/enable operations.

### Subscription with Trial Period

```php
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->trialDays(14)  // 14-day free trial
    ->with('paystack')
    ->subscribe();
```

### Subscription with Custom Start Date

```php
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->startDate('2024-02-01')  // Start on specific date (Y-m-d format)
    ->with('paystack')
    ->subscribe();
```

### Subscription with Quantity

```php
// For multi-seat subscriptions
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->quantity(5)  // 5 seats/licenses
    ->with('paystack')
    ->subscribe();
```

### Subscription with Authorization Code

```php
// If customer already has a saved card authorization
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->authorization('AUTH_abc123')  // Saved authorization code
    ->with('paystack')
    ->subscribe();
```

### Subscription with Metadata

```php
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->metadata([
        'user_id' => auth()->id(),
        'order_id' => 12345,
        'subscription_type' => 'premium',
        'referral_code' => 'REF123',
    ])
    ->with('paystack')
    ->subscribe();
```

### Complete Subscription Example

```php
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->quantity(1)
    ->trialDays(7)
    ->startDate('2024-02-01')
    ->metadata(['user_id' => auth()->id()])
    ->with('paystack')
    ->subscribe();
```

### Subscription Validation

The `SubscriptionRequestDTO` automatically validates:
- **Customer**: Must not be empty (email or customer code)
- **Plan**: Must not be empty (plan code)
- **Quantity**: Must be at least 1 (if provided)
- **Trial Days**: Cannot be negative (if provided)

Invalid subscriptions will throw `InvalidArgumentException` before making any API calls.

---

## Recommended Subscription Flow (Redirect to Payment)

### Step-by-Step Implementation

#### Step 1: User Selects Plan - Redirect to Payment

```php
// In your SubscriptionController
public function subscribe(Request $request)
{
    $planCode = $request->input('plan_code');
    $user = auth()->user();
    
    // Get plan details
    $plan = Payment::subscription()
        ->plan($planCode)
        ->with('paystack')
        ->getPlan();
    
    // Redirect user to payment page
    // This charges the first payment and gets authorization
    return Payment::amount($plan['amount'] / 100)  // Convert from kobo to major units
        ->currency($plan['currency'])
        ->email($user->email)
        ->callback(route('subscription.callback', [
            'plan_code' => $planCode,
            'user_id' => $user->id,
        ]))
        ->metadata([
            'plan_code' => $planCode,
            'user_id' => $user->id,
            'subscription_flow' => true,  // Flag for callback handler
        ])
        ->with('paystack')
        ->redirect();  // User redirected to Paystack payment page
}
```

#### Step 2: Payment Callback - Create Subscription

```php
// Handle payment callback
public function subscriptionCallback(Request $request)
{
    $reference = $request->input('reference');
    $planCode = $request->input('plan_code');
    $userId = $request->input('user_id');
    
    try {
        // Verify the payment was successful
        $verification = Payment::verify($reference, 'paystack');
        
        if (!$verification->isSuccessful()) {
            return redirect()->route('subscription.failed')
                ->with('error', 'Payment was not successful. Please try again.');
        }
        
        // Extract authorization code from verification response
        // This is now available in VerificationResponseDTO!
        $authorizationCode = $verification->authorizationCode;
        
        if (!$authorizationCode) {
            // Fallback: Create subscription without authorization
            // Paystack will send email to customer for authorization
            $subscription = Payment::subscription()
                ->customer($verification->customer['email'] ?? $user->email)
                ->plan($planCode)
                ->with('paystack')
                ->subscribe();
        } else {
            // Create subscription with authorization - immediate activation!
            $subscription = Payment::subscription()
                ->customer($verification->customer['email'] ?? $user->email)
                ->plan($planCode)
                ->authorization($authorizationCode)  // Use saved authorization
                ->with('paystack')
                ->subscribe();
        }
        
        // Save subscription to database
        DB::table('subscriptions')->insert([
            'user_id' => $userId,
            'subscription_code' => $subscription->subscriptionCode,
            'email_token' => $subscription->emailToken,  // CRITICAL: Save this!
            'plan_code' => $planCode,
            'status' => $subscription->status,
            'amount' => $subscription->amount,
            'authorization_code' => $authorizationCode,  // Save for future use
            'next_payment_date' => $subscription->nextPaymentDate,
            'created_at' => now(),
        ]);
        
        // Update user's subscription status
        User::where('id', $userId)->update([
            'subscription_status' => 'active',
            'subscription_code' => $subscription->subscriptionCode,
        ]);
        
        return redirect()->route('subscription.success')
            ->with('subscription', $subscription);
            
    } catch (\Exception $e) {
        logger()->error('Subscription creation failed', [
            'reference' => $reference,
            'plan_code' => $planCode,
            'user_id' => $userId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return redirect()->route('subscription.failed')
            ->with('error', 'Failed to create subscription. Please contact support.');
    }
}
```

#### Step 3: Routes Setup

```php
// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
    Route::get('/subscription/callback', [SubscriptionController::class, 'subscriptionCallback'])->name('subscription.callback');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
    Route::get('/subscription/failed', [SubscriptionController::class, 'failed'])->name('subscription.failed');
});
```

### Complete Example: Full Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use KenDeNigerian\PayZephyr\Facades\Payment;

class SubscriptionController extends Controller
{
    /**
     * Show subscription plans page
     */
    public function plans()
    {
        $plans = [
            [
                'code' => 'PLN_basic',
                'name' => 'Basic Plan',
                'amount' => 5000.00,
                'interval' => 'monthly',
            ],
            [
                'code' => 'PLN_pro',
                'name' => 'Pro Plan',
                'amount' => 15000.00,
                'interval' => 'monthly',
            ],
        ];
        
        return view('subscriptions.plans', compact('plans'));
    }
    
    /**
     * User selects plan - redirect to payment
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_code' => 'required|string',
        ]);
        
        $planCode = $request->input('plan_code');
        $user = auth()->user();
        
        // Get plan details from Paystack
        $plan = Payment::subscription()
            ->plan($planCode)
            ->with('paystack')
            ->getPlan();
        
        // Redirect to payment page
        return Payment::amount($plan['amount'] / 100)
            ->currency($plan['currency'])
            ->email($user->email)
            ->callback(route('subscription.callback', [
                'plan_code' => $planCode,
                'user_id' => $user->id,
            ]))
            ->metadata([
                'plan_code' => $planCode,
                'user_id' => $user->id,
                'subscription_flow' => true,
            ])
            ->with('paystack')
            ->redirect();
    }
    
    /**
     * Payment callback - create subscription
     */
    public function callback(Request $request)
    {
        $reference = $request->input('reference');
        $planCode = $request->input('plan_code');
        $userId = $request->input('user_id');
        
        if (!$reference || !$planCode || !$userId) {
            return redirect()->route('subscription.failed')
                ->with('error', 'Invalid callback parameters.');
        }
        
        try {
            // Verify payment
            $verification = Payment::verify($reference, 'paystack');
            
            if (!$verification->isSuccessful()) {
                return redirect()->route('subscription.failed')
                    ->with('error', 'Payment verification failed.');
            }
            
            $user = \App\Models\User::findOrFail($userId);
            $authorizationCode = $verification->authorizationCode;
            
            // Create subscription
            $subscription = Payment::subscription()
                ->customer($user->email)
                ->plan($planCode)
                ->with('paystack');
            
            if ($authorizationCode) {
                $subscription->authorization($authorizationCode);
            }
            
            $subscriptionResult = $subscription->subscribe();
            
            // Save subscription
            DB::table('subscriptions')->insert([
                'user_id' => $userId,
                'subscription_code' => $subscriptionResult->subscriptionCode,
                'email_token' => $subscriptionResult->emailToken,
                'plan_code' => $planCode,
                'status' => $subscriptionResult->status,
                'amount' => $subscriptionResult->amount,
                'authorization_code' => $authorizationCode,
                'next_payment_date' => $subscriptionResult->nextPaymentDate,
                'created_at' => now(),
            ]);
            
            // Update user
            $user->update([
                'subscription_status' => 'active',
                'subscription_code' => $subscriptionResult->subscriptionCode,
            ]);
            
            return redirect()->route('subscription.success')
                ->with('subscription', $subscriptionResult);
                
        } catch (\Exception $e) {
            logger()->error('Subscription callback failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('subscription.failed')
                ->with('error', 'Subscription creation failed. Please contact support.');
        }
    }
    
    /**
     * Success page
     */
    public function success()
    {
        $subscription = session('subscription');
        
        return view('subscriptions.success', compact('subscription'));
    }
    
    /**
     * Failed page
     */
    public function failed()
    {
        $error = session('error', 'An error occurred during subscription.');
        
        return view('subscriptions.failed', compact('error'));
    }
}
```

### Alternative: Direct Subscription (No Redirect)

```php
// Direct subscription creation (no redirect)
$subscription = Payment::subscription()
    ->customer('customer@example.com')
    ->plan('PLN_abc123')
    ->with('paystack')
    ->subscribe();
```

---

## Managing Subscriptions

### Get Subscription Details

```php
$subscription = Payment::subscription('SUB_xyz789')
    ->with('paystack')
    ->get();

// Check status
if ($subscription->isActive()) {
    // Active subscription
}

// Access properties
$subscription->subscriptionCode;
$subscription->status;
$subscription->nextPaymentDate;
$subscription->emailToken;
```

### Cancel Subscription

```php
$cancelled = Payment::subscription('SUB_xyz789')
    ->token('email_token_here')  // Required: token from subscription creation
    ->with('paystack')
    ->cancel();

// Or pass token as parameter
$cancelled = Payment::subscription('SUB_xyz789')
    ->with('paystack')
    ->cancel('email_token_here');
```

### Enable Cancelled Subscription

```php
$enabled = Payment::subscription('SUB_xyz789')
    ->token('email_token_here')
    ->with('paystack')
    ->enable();
```

### List Subscriptions

```php
// List all subscriptions
$subscriptions = Payment::subscription()
    ->with('paystack')
    ->list();

// With pagination
$subscriptions = Payment::subscription()
    ->perPage(20)
    ->page(2)
    ->with('paystack')
    ->list();

// Filter by customer
$subscriptions = Payment::subscription()
    ->with('paystack')
    ->list('customer@example.com');
```

---

## Plan Management

### Get Plan Details

```php
$plan = Payment::subscription()
    ->plan('PLN_abc123')
    ->with('paystack')
    ->getPlan();

// Access properties
$plan['plan_code'];
$plan['amount'];  // In kobo (minor units)
```

### Update Plan

```php
// Update plan name and amount
$updated = Payment::subscription()
    ->plan('PLN_abc123')
    ->planUpdates([
        'name' => 'Updated Plan Name',
        'amount' => 600000,  // Amount in kobo (₦6,000)
    ])
    ->with('paystack')
    ->updatePlan();

// Update only specific fields
$updated = Payment::subscription()
    ->plan('PLN_abc123')
    ->planUpdates([
        'description' => 'New description',
    ])
    ->with('paystack')
    ->updatePlan();
```

### List Plans

```php
$plans = Payment::subscription()
    ->with('paystack')
    ->listPlans();

// With pagination
$plans = Payment::subscription()
    ->perPage(20)
    ->page(1)
    ->with('paystack')
    ->listPlans();
```

---

## Complete Workflow Examples

### Example 1: Complete Subscription Setup

```php
// Step 1: Create plan
$planDTO = new SubscriptionPlanDTO(
    name: 'Pro Monthly',
    amount: 10000.00,
    interval: 'monthly',
    currency: 'NGN'
);

$plan = Payment::subscription()
    ->planData($planDTO)
    ->with('paystack')
    ->createPlan();

// Step 2: Create subscription
$subscription = Payment::subscription()
    ->customer(auth()->user()->email)
    ->plan($plan['plan_code'])
    ->trialDays(14)
    ->with('paystack')
    ->subscribe();

// Step 3: Save to database
DB::table('subscriptions')->insert([
    'user_id' => auth()->id(),
    'subscription_code' => $subscription->subscriptionCode,
    'email_token' => $subscription->emailToken,
    'plan_code' => $plan['plan_code'],
    'status' => $subscription->status,
]);
```

### Example 2: Subscription Management in Controller

```php
class SubscriptionController extends Controller
{
    public function create(Request $request)
    {
        $subscription = Payment::subscription()
            ->customer(auth()->user()->email)
            ->plan($request->plan_code)
            ->with('paystack')
            ->subscribe();

        // Save to database
        auth()->user()->subscriptions()->create([
            'subscription_code' => $subscription->subscriptionCode,
            'email_token' => $subscription->emailToken,
            'status' => $subscription->status,
        ]);
    }

    public function cancel(Request $request, string $code)
    {
        $cancelled = Payment::subscription($code)
            ->token($request->token)
            ->with('paystack')
            ->cancel();

        // Update database
        auth()->user()->subscriptions()
            ->where('subscription_code', $code)
            ->update(['status' => 'cancelled']);
    }
}
```

### Example 3: Subscription Status Check Middleware

```php
class CheckActiveSubscription
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user->subscription_code) {
            return redirect()->route('subscription.create');
        }

        $subscription = Payment::subscription($user->subscription_code)
            ->with('paystack')
            ->get();

        if (!$subscription->isActive()) {
            return redirect()->route('subscription.expired');
        }

        return $next($request);
    }
}
```

### Example 4: Webhook Handler for Subscription Events

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use KenDeNigerian\PayZephyr\Facades\Payment;
use Illuminate\Support\Facades\DB;

class SubscriptionWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Validate webhook (handled by PayZephyr middleware)
        $payload = $request->all();

        // Handle subscription events
        if ($payload['event'] === 'subscription.create') {
            $this->handleSubscriptionCreated($payload['data']);
        } elseif ($payload['event'] === 'subscription.disable') {
            $this->handleSubscriptionCancelled($payload['data']);
        } elseif ($payload['event'] === 'subscription.enable') {
            $this->handleSubscriptionEnabled($payload['data']);
        } elseif ($payload['event'] === 'invoice.payment_failed') {
            $this->handlePaymentFailed($payload['data']);
        } elseif ($payload['event'] === 'invoice.payment_succeeded') {
            $this->handlePaymentSucceeded($payload['data']);
        }

        return response()->json(['status' => 'success']);
    }

    protected function handleSubscriptionCreated($data)
    {
        // Update subscription status in database
        DB::table('user_subscriptions')
            ->where('subscription_code', $data['subscription_code'])
            ->update([
                'status' => $data['status'],
                'next_payment_date' => $data['next_payment_date'],
            ]);
    }

    protected function handlePaymentSucceeded($data)
    {
        // Update subscription after successful payment
        try {
            $subscription = Payment::subscription($data['subscription']['subscription_code'])
                ->with('paystack')
                ->get();

            DB::table('user_subscriptions')
                ->where('subscription_code', $subscription->subscriptionCode)
                ->update([
                    'status' => $subscription->status,
                    'next_payment_date' => $subscription->nextPaymentDate,
                ]);
        } catch (\Exception $e) {
            logger()->error('Failed to update subscription from webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    protected function handlePaymentFailed($data)
    {
        // Handle failed payment - maybe send notification
        logger()->warning('Subscription payment failed', [
            'subscription_code' => $data['subscription']['subscription_code'],
        ]);
    }
}
```

---

## Error Handling

### Try-Catch Examples

```php
use KenDeNigerian\PayZephyr\Exceptions\SubscriptionException;
use KenDeNigerian\PayZephyr\Exceptions\PlanException;

// Creating subscription with error handling
try {
    $subscription = Payment::subscription()
        ->customer('customer@example.com')
        ->plan('PLN_abc123')
        ->with('paystack')
        ->subscribe();
} catch (SubscriptionException $e) {
    // Handle subscription-specific errors
    logger()->error('Subscription creation failed', [
        'error' => $e->getMessage(),
        'context' => $e->getContext(),
    ]);
    
    return response()->json([
        'error' => 'Failed to create subscription',
        'message' => $e->getMessage(),
    ], 400);
} catch (\Exception $e) {
    // Handle other errors
    return response()->json([
        'error' => 'An unexpected error occurred',
    ], 500);
}

// Creating plan with error handling
try {
    $plan = Payment::subscription()
        ->planData($planDTO)
        ->with('paystack')
        ->createPlan();
} catch (PlanException $e) {
    logger()->error('Plan creation failed', [
        'error' => $e->getMessage(),
    ]);
    
    return back()->withErrors(['plan' => $e->getMessage()]);
}
```

### Validation Before Operations

```php
// Validate subscription code exists before operations
try {
    $subscription = Payment::subscription($subscriptionCode)
        ->with('paystack')
        ->get();
} catch (SubscriptionException $e) {
    if (str_contains($e->getMessage(), 'not found')) {
        return response()->json([
            'error' => 'Subscription not found',
        ], 404);
    }
    
    throw $e;
}

// Validate token before cancel/enable
if (!$emailToken) {
    return response()->json([
        'error' => 'Email token is required',
    ], 400);
}

$cancelled = Payment::subscription($subscriptionCode)
    ->token($emailToken)
    ->with('paystack')
    ->cancel();
```

---

## Best Practices

### 1. Store Subscription Data

Always save subscription codes and email tokens to your database immediately after creation (see [Basic Subscription](#basic-subscription) example).

### 2. Use Provider Selection

Always specify the provider explicitly: `->with('paystack')`

### 3. Handle Webhooks

Set up webhook handlers to keep subscription status in sync (see [Example 4](#example-4-webhook-handler-for-subscription-events)).

### 4. Check Subscription Status Regularly

Use `$subscription->isActive()` to check status (see [Get Subscription Details](#get-subscription-details)).

### 5. Use Metadata for Tracking

Include relevant data in metadata (see [Subscription with Metadata](#subscription-with-metadata)).

### 6. Error Handling Pattern

Always wrap subscription operations in try-catch blocks (see [Error Handling](#error-handling) section).

---

## Security Considerations

### 1. Email Token Security

The email token is required for cancel/enable operations. Treat it as sensitive data:

- **Store securely**: Use encrypted database columns if possible
- **Never expose**: Don't include in URLs or client-side code
- **Validate ownership**: Always verify the user owns the subscription before allowing cancel/enable

### 2. Webhook Security

- **Enable signature verification**: Always verify webhook signatures in production
- **Use HTTPS**: All webhook URLs must use HTTPS
- **Validate events**: Verify event types before processing
- **Idempotency**: Handle duplicate webhook deliveries gracefully

### 3. Amount Validation

- **Validate amounts**: Always validate amounts before creating plans or subscriptions
- **Use DTOs**: The `SubscriptionPlanDTO` and `SubscriptionRequestDTO` automatically validate amounts
- **Check currency**: Ensure currency matches your business requirements

### 4. Error Handling

- **Don't expose internals**: Never expose internal error messages to users
- **Log securely**: Log errors with context but sanitize sensitive data
- **Monitor failures**: Set up alerts for subscription failures

---

## Developer Guide: Adding Subscription Support to a Driver

If you're a developer and want to add subscription support for a new driver, follow this guide.

### Prerequisites

1. **Understand the Architecture**: Read the [Architecture Guide](architecture.md) to understand how drivers work
2. **Review PaystackDriver**: Study `src/Drivers/PaystackDriver.php` and `src/Traits/PaystackSubscriptionMethods.php`
3. **Understand the Interface**: Review `src/Contracts/SupportsSubscriptionsInterface.php`

### Step 1: Implement the Interface

Your driver class must implement `SupportsSubscriptionsInterface`:

```php
<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use KenDeNigerian\PayZephyr\Contracts\SupportsSubscriptionsInterface;
use KenDeNigerian\PayZephyr\Contracts\DriverInterface;

final class YourDriver extends AbstractDriver implements SupportsSubscriptionsInterface
{
    // Your driver implementation
}
```

### Step 2: Create Subscription Methods Trait (Recommended)

Following the Single Responsibility Principle (SRP), create a trait for subscription methods:

```php
<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Traits;

use KenDeNigerian\PayZephyr\DataObjects\SubscriptionPlanDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionResponseDTO;
use KenDeNigerian\PayZephyr\Exceptions\PlanException;
use KenDeNigerian\PayZephyr\Exceptions\SubscriptionException;
use Throwable;

trait YourProviderSubscriptionMethods
{
    /**
     * Create a subscription plan
     *
     * @return array<string, mixed>
     *
     * @throws PlanException If the plan creation fails
     */
    public function createPlan(SubscriptionPlanDTO $plan): array
    {
        try {
            // Convert plan DTO to provider-specific format
            $payload = [
                'name' => $plan->name,
                'amount' => $plan->getAmountInMinorUnits(), // Use DTO method for conversion
                'interval' => $this->mapInterval($plan->interval), // Map to provider format
                'currency' => $plan->currency,
                // ... provider-specific fields
            ];

            $response = $this->makeRequest('POST', '/plans', [
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            // Validate response
            if (!isset($data['id'])) {
                throw new PlanException('Failed to create subscription plan');
            }

            $this->log('info', 'Subscription plan created', [
                'plan_id' => $data['id'],
                'name' => $plan->name,
            ]);

            // Return normalized format
            return [
                'plan_code' => $data['id'],
                'name' => $data['name'],
                'amount' => $data['amount'],
                'interval' => $this->normalizeInterval($data['interval']),
                'currency' => $data['currency'],
            ];
        } catch (PlanException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to create plan', [
                'error' => $e->getMessage(),
            ]);
            throw new PlanException(
                'Failed to create plan: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create a subscription
     *
     * @throws SubscriptionException If subscription creation fails
     */
    public function createSubscription(SubscriptionRequestDTO $request): SubscriptionResponseDTO
    {
        try {
            $payload = [
                'customer' => $request->customer,
                'plan' => $request->plan,
                'quantity' => $request->quantity ?? 1,
                // ... provider-specific fields
            ];

            if ($request->trialDays) {
                $payload['trial_period_days'] = $request->trialDays;
            }

            if ($request->startDate) {
                $payload['start_date'] = $request->startDate;
            }

            if ($request->authorization) {
                $payload['authorization'] = $request->authorization;
            }

            if (!empty($request->metadata)) {
                $payload['metadata'] = $request->metadata;
            }

            $response = $this->makeRequest('POST', '/subscriptions', [
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            // Validate response
            if (!isset($data['id'])) {
                throw new SubscriptionException('Failed to create subscription');
            }

            $this->log('info', 'Subscription created', [
                'subscription_id' => $data['id'],
                'customer' => $request->customer,
                'plan' => $request->plan,
            ]);

            // Return normalized SubscriptionResponseDTO
            return new SubscriptionResponseDTO(
                subscriptionCode: $data['id'],
                status: $this->normalizeStatus($data['status']),
                customer: $data['customer']['email'] ?? $request->customer,
                plan: $data['plan']['name'] ?? $request->plan,
                amount: ($data['amount'] ?? 0) / 100, // Convert from minor to major units
                currency: $data['currency'] ?? 'USD',
                nextPaymentDate: $data['next_payment_date'] ?? null,
                emailToken: $data['email_token'] ?? null, // Provider-specific token
                metadata: $data['metadata'] ?? [],
                provider: $this->getName(),
            );
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to subscribe', [
                'error' => $e->getMessage(),
            ]);
            throw new SubscriptionException(
                'Failed to subscribe: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    // Implement other required methods:
    // - updatePlan()
    // - getPlan()
    // - listPlans()
    // - getSubscription()
    // - cancelSubscription()
    // - enableSubscription()
    // - listSubscriptions()

    /**
     * Map interval from unified format to provider format
     */
    protected function mapInterval(string $interval): string
    {
        return match ($interval) {
            'daily' => 'day',
            'weekly' => 'week',
            'monthly' => 'month',
            'annually' => 'year',
            default => $interval,
        };
    }

    /**
     * Normalize interval from provider format to unified format
     */
    protected function normalizeInterval(string $interval): string
    {
        return match (strtolower($interval)) {
            'day' => 'daily',
            'week' => 'weekly',
            'month' => 'monthly',
            'year' => 'annually',
            default => $interval,
        };
    }

    /**
     * Normalize status from provider format to unified format
     */
    protected function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'active', 'enabled' => 'active',
            'cancelled', 'disabled', 'canceled' => 'cancelled',
            'completed', 'ended' => 'completed',
            default => strtolower($status),
        };
    }
}
```

### Step 3: Use the Trait in Your Driver

```php
<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use KenDeNigerian\PayZephyr\Contracts\SupportsSubscriptionsInterface;
use KenDeNigerian\PayZephyr\Traits\YourProviderSubscriptionMethods;

final class YourDriver extends AbstractDriver implements SupportsSubscriptionsInterface
{
    use YourProviderSubscriptionMethods;

    protected string $name = 'yourprovider';

    // Your driver implementation
}
```

### Step 4: Implement All Required Methods

The `SupportsSubscriptionsInterface` requires these methods:

1. **`createPlan(SubscriptionPlanDTO $plan): array`** - Create a subscription plan
2. **`updatePlan(string $planCode, array $updates): array`** - Update a plan
3. **`getPlan(string $planCode): array`** - Get plan details
4. **`listPlans(?int $perPage = 50, ?int $page = 1): array`** - List all plans
5. **`createSubscription(SubscriptionRequestDTO $request): SubscriptionResponseDTO`** - Create a subscription
6. **`getSubscription(string $subscriptionCode): SubscriptionResponseDTO`** - Get subscription details
7. **`cancelSubscription(string $subscriptionCode, string $token): SubscriptionResponseDTO`** - Cancel a subscription
8. **`enableSubscription(string $subscriptionCode, string $token): SubscriptionResponseDTO`** - Enable a cancelled subscription
9. **`listSubscriptions(?int $perPage = 50, ?int $page = 1, ?string $customer = null): array`** - List subscriptions

### Step 5: Handle Amount Conversion

**CRITICAL**: Always use the DTO's `getAmountInMinorUnits()` method for plan creation:

```php
$payload = [
    'amount' => $plan->getAmountInMinorUnits(), // ✅ Correct - converts to minor units
    // NOT: 'amount' => $plan->amount * 100, // ❌ Wrong
];
```

When returning amounts from API responses, convert from minor to major units:

```php
amount: ($result['amount'] ?? 0) / 100, // Convert from minor to major units
```

### Step 6: Error Handling

Always use the specific exceptions:

- **`PlanException`** for plan-related errors
- **`SubscriptionException`** for subscription-related errors

Never use generic `PaymentException` for subscription operations.

### Step 7: Write Tests

Create comprehensive tests for all subscription operations:

```php
<?php

use KenDeNigerian\PayZephyr\Drivers\YourDriver;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionPlanDTO;

test('your driver creates plan successfully', function () {
    $driver = new YourDriver($config);
    // ... test implementation
});

// Test all methods:
// - createPlan()
// - updatePlan()
// - getPlan()
// - listPlans()
// - createSubscription()
// - getSubscription()
// - cancelSubscription()
// - enableSubscription()
// - listSubscriptions()
```

### Step 8: Update Documentation

1. Update this file (`docs/SUBSCRIPTIONS.md`) to include your provider in the support table
2. Add provider-specific notes if needed
3. Update `docs/providers.md` if applicable

### Step 9: Submit a Pull Request

1. Ensure all tests pass
2. Run PHPStan: `composer analyse`
3. Run Pint: `composer format`
4. Submit your PR with:
   - Clear description of changes
   - Test coverage
   - Documentation updates

### Key Principles

1. **Follow SRP**: Use traits for subscription methods (like `PaystackSubscriptionMethods`)
2. **Use DTOs**: Always use `SubscriptionPlanDTO`, `SubscriptionRequestDTO`, and `SubscriptionResponseDTO`
3. **Normalize Data**: Convert provider-specific formats to unified formats
4. **Error Handling**: Use specific exceptions (`PlanException`, `SubscriptionException`)
5. **Amount Conversion**: Always use DTO methods for amount conversion
6. **Logging**: Log all operations for debugging
7. **Testing**: Write comprehensive tests

### Example: Complete Implementation

See `src/Drivers/PaystackDriver.php` and `src/Traits/PaystackSubscriptionMethods.php` for a complete reference implementation.

---

## Summary

Subscriptions in PayZephyr follow the same unified fluent builder pattern as payments:

- **Builder methods** return `$this` for chaining
- **Final action methods** execute the operation (use `subscribe()` for clarity - `create()` is also available as an alias)
- **Provider selection** uses `with()` or `using()` (same as Payment)
- **Consistent API** across all operations
- **Helper function support**: Both `Payment::subscription()` and `payment()->subscription()` work identically

**Current Status**: Only PaystackDriver supports subscriptions. Support for other providers will be added in future releases.

**For Developers**: If you want to add subscription support for a new driver, see the [Developer Guide](#developer-guide-adding-subscription-support-to-a-driver) section above.

This provides a seamless experience when working with both one-time payments and recurring subscriptions.
