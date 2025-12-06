# Architecture

## Overview

The Payments Router package follows clean architecture principles with clear separation of concerns:

```
┌─────────────────────────────────────────────┐
│           Facades & Helpers                  │
│     (Payment::, payment())                   │
└──────────────┬──────────────────────────────┘
               │
┌──────────────▼──────────────────────────────┐
│          Payment (Fluent API)                │
│    Builds ChargeRequest & calls Manager      │
└──────────────┬──────────────────────────────┘
               │
┌──────────────▼──────────────────────────────┐
│         PaymentManager                       │
│   - Manages driver instances                 │
│   - Handles fallback logic                   │
│   - Coordinates health checks                │
└──────────────┬──────────────────────────────┘
               │
┌──────────────▼──────────────────────────────┐
│           Drivers Layer                      │
│  AbstractDriver ← Implements DriverInterface │
│         ├─ PaystackDriver                    │
│         ├─ FlutterwaveDriver                 │
│         ├─ MonnifyDriver                     │
│         ├─ StripeDriver                      │
│         └─ PayPalDriver                      │
└──────────────┬──────────────────────────────┘
               │
┌──────────────▼──────────────────────────────┐
│      External Payment APIs                   │
│   (Paystack, Stripe, etc.)                   │
└──────────────────────────────────────────────┘
```

## Core Components

### 1. Contracts (Interfaces)

- **DriverInterface**: Defines the contract all payment drivers must implement
- **CurrencyConverterInterface**: Defines currency conversion contract

### 2. Data Transfer Objects (DTOs)

- **ChargeRequest**: Standardized payment request
- **ChargeResponse**: Standardized charge response  
- **VerificationResponse**: Standardized verification response

These DTOs ensure consistent data structures across all providers.

### 3. Drivers

Each driver extends `AbstractDriver` and implements `DriverInterface`:

**AbstractDriver** provides:
- HTTP client initialization
- Request/response handling
- Health check caching
- Logging
- Currency support checking
- Reference generation

**Individual Drivers**:
- Paystack: Nigerian payments via REST API
- Flutterwave: African payments via REST API
- Monnify: Nigerian payments with OAuth2
- Stripe: Global payments via official SDK
- PayPal: Global payments via REST API

### 4. PaymentManager

The manager:
- Instantiates and caches driver instances
- Resolves driver classes from configuration
- Manages fallback chains
- Coordinates health checks before charges
- Handles provider failures gracefully

### 5. Payment (Fluent API)

Provides a clean, expressive interface:
```php
// Builder methods can be chained in any order
// with() or using() can be called anywhere in the chain
// redirect() or charge() must be called last to execute
Payment::amount(1000)
    ->currency('NGN')
    ->email('user@example.com')
    ->with('paystack') // or ->using('paystack')
    ->redirect(); // Must be called last
```

### 6. Service Provider

Registers:
- PaymentManager as singleton
- Payment class binding
- Config file
- Migrations
- Webhook routes

## Data Flow

### Charge Flow

1. User calls `Payment::amount()->email()->redirect()` (builder methods can be in any order)
2. Payment builds ChargeRequest DTO from all chained builder methods
3. PaymentManager receives request via `chargeWithFallback()`
4. Manager gets fallback provider chain (from `with()`/`using()` or config defaults)
5. For each provider in chain:
   - Check if enabled
   - Run health check (if enabled)
   - Verify currency support
   - Attempt charge
   - Return on success, continue on failure
6. Driver makes API call
7. Driver returns ChargeResponse DTO
8. Transaction logged to database (if enabled)
9. User redirected to payment page (if `redirect()` was called) or response returned (if `charge()` was called)

### Verification Flow

1. User calls `Payment::verify($reference)` or `Payment::verify($reference, $provider)`
   - **Note:** `verify()` is a standalone method, NOT chainable
2. Manager tries all enabled providers (or specified one)
3. First successful verification returns
4. Transaction status updated in database (if logging enabled)
5. VerificationResponse DTO returned
6. Application handles result

### Webhook Flow

1. Provider POSTs to `/payments/webhook/{provider}`
2. WebhookController receives request
3. Manager loads appropriate driver
4. Driver validates signature
5. Event dispatched: `payments.webhook.{provider}`
6. Application handles event
7. 200 response sent to provider

## Design Patterns

### 1. Abstract Factory Pattern
PaymentManager acts as factory for driver instances.

### 2. Strategy Pattern
Each driver is a strategy for processing payments.

### 3. Facade Pattern
Payment facade provides simplified interface.

### 4. DTO Pattern
Consistent data structures across providers.

### 5. Chain of Responsibility
Fallback mechanism tries providers in sequence.

## Error Handling

Exception hierarchy:
```
Exception
└── PaymentException (base)
    ├── DriverNotFoundException
    ├── InvalidConfigurationException
    ├── ChargeException
    ├── VerificationException
    ├── WebhookException
    ├── CurrencyException
    └── ProviderException (all providers failed)
```

Each exception can carry context via `setContext()`.

## Configuration

Configuration is hierarchical:
- Package defaults (config/payments.php)
- Environment variables (.env)
- Runtime overrides

## Security

- Webhook signatures verified by default
- API keys never exposed in logs
- HTTPS enforced (except testing mode)
- Rate limiting supported
- Input validation in DTOs

## Extensibility

### Adding New Providers

1. Create driver class extending `AbstractDriver`
2. Implement `DriverInterface` methods
3. Add configuration to `config/payments.php`
4. Register in PaymentManager's driver map

### Custom DTOs

DTOs can be extended for provider-specific features while maintaining compatibility.

## Testing Strategy

- Unit tests for each driver
- Integration tests for manager
- Feature tests for facade
- Mock external APIs
- Test fallback scenarios
- Test error conditions

## Performance Considerations

- Driver instances cached after creation
- Health checks cached (configurable TTL)
- Minimal dependencies
- Lazy loading of drivers
- Efficient HTTP client reuse

## Future Enhancements

- Currency converter implementation
- Refund operations
- Subscription support
- Multi-tenancy
- Dashboard for monitoring
- Advanced routing (amount-based, geo-based)
