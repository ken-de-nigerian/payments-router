# Testing Guide

This document provides information about the comprehensive test suite for PayZephyr.

## Test Structure

The test suite is organized into:

- **Unit Tests** (`tests/Unit/`): Test individual components in isolation
- **Feature Tests** (`tests/Feature/`): Test complete workflows and integrations

## New Test Files

### Interface Tests (`InterfacesTest.php`)
Tests that verify:
- Interfaces are properly bound in the service container
- Dependency injection works with interfaces
- Backward compatibility with concrete classes

### Webhook Request Tests (`WebhookRequestTest.php`)
Tests that verify:
- Form request validation rules
- Signature authorization logic
- Provider-specific validation handling

### Process Webhook Job Tests (`ProcessWebhookJobTest.php`)
Tests that verify:
- Job queues and processes webhooks correctly
- Database transaction updates
- Event dispatching
- Error handling and retries

### Webhook Received Event Tests (`WebhookReceivedEventTest.php`)
Tests that verify:
- Event can be dispatched
- Event properties are correct
- Event serialization for queues

### Payment Channel Enum Tests (`PaymentChannelEnumTest.php`)
Tests that verify:
- Enum values are correct
- Label generation works
- Enum creation from values

### API Resources Tests (`ApiResourcesTest.php`)
Tests that verify:
- ChargeResource transformation
- VerificationResource transformation
- Proper handling of null values

### Install Command Tests (`InstallCommandTest.php`)
Tests that verify:
- Command registration
- Config and migration publishing
- Command execution

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Unit/InterfacesTest.php

# Run with coverage
php artisan test --coverage
```

## Test Configuration

The test suite uses:
- **Orchestra Testbench** for Laravel package testing
- **Pest PHP** for test syntax
- **SQLite in-memory database** for fast test execution
- **Mockery** for mocking dependencies

## Key Testing Patterns

### Mocking Payment Drivers

```php
$mockDriver = Mockery::mock(\KenDeNigerian\PayZephyr\Contracts\DriverInterface::class);
$mockDriver->shouldReceive('extractWebhookReference')
    ->andReturn('ref_123');
```

### Testing Queued Jobs

```php
Queue::fake();

// Perform action that queues job
ProcessWebhook::dispatch('paystack', $payload);

Queue::assertPushed(ProcessWebhook::class);
```

### Testing Events

```php
Event::fake();

// Perform action that dispatches event
WebhookReceived::dispatch('paystack', $payload, 'ref_123');

Event::assertDispatched(WebhookReceived::class);
```

### Testing Form Requests

```php
$request = WebhookRequest::create('/payments/webhook/paystack', 'POST', $payload);
$request->setRouteResolver(function () use ($request) {
    $route = Route::get('/payments/webhook/{provider}', fn () => null);
    $route->setParameter('provider', 'paystack');
    return $route;
});

expect($request->authorize())->toBeTrue();
```

## Coverage Goals

The test suite aims for:
- **90%+ code coverage** for core functionality
- **100% coverage** for critical paths (webhook processing, payment verification)
- **Comprehensive edge case testing** for error handling

## Continuous Integration

Tests are designed to run in CI/CD pipelines with:
- Fast execution (< 30 seconds for full suite)
- No external dependencies
- Deterministic results
- Clear failure messages

