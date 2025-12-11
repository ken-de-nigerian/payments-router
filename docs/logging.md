# Logging Configuration

PayZephyr uses Laravel's logging system to track payment operations, webhook processing, and errors. To enable dedicated logging for payment-related events, you need to configure a dedicated log channel.

## Setup

Add the following configuration to your `config/logging.php` file:

```php
'channels' => [
    // ... your existing channels ...

    'payments' => [
        'driver' => 'daily',
        'path' => storage_path('logs/payments.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => env('PAYMENTS_LOG_RETENTION_DAYS', 14),
        'replace_placeholders' => true,
    ],
],
```

## Configuration Options

### Driver
- `daily` - Creates a new log file each day (recommended for production)
- `single` - Single log file (useful for development)
- `stack` - Combine multiple channels

### Path
The location where log files will be stored. Default: `storage/logs/payments.log`

### Level
The minimum log level to record:
- `debug` - All events (development)
- `info` - Informational messages and above
- `warning` - Warnings and errors
- `error` - Errors only

### Days
Number of days to retain log files (only applies to `daily` driver). Default: 14 days

## Environment Variables

You can configure logging behavior using environment variables:

```env
# Log level for payments channel
LOG_LEVEL=debug

# Number of days to retain payment logs
PAYMENTS_LOG_RETENTION_DAYS=30
```

## What Gets Logged

PayZephyr logs the following events:

### Webhook Processing
- Webhook received and queued
- Webhook processing success/failure
- Transaction updates from webhooks
- Invalid webhook signatures

### Payment Operations
- Payment charge attempts
- Payment verification requests
- Provider fallback attempts
- Health check results

### Errors
- Network errors
- Configuration errors
- Provider API errors
- Database transaction failures

## Example Log Entries

```
[2024-01-15 10:30:45] payments.INFO: Webhook queued for processing {"provider":"paystack","ip":"192.168.1.1"}
[2024-01-15 10:30:46] payments.INFO: Webhook processed for paystack {"reference":"PAYSTACK_123","event":"charge.success"}
[2024-01-15 10:30:47] payments.INFO: Transaction updated from webhook {"reference":"PAYSTACK_123","status":"success","provider":"paystack"}
[2024-01-15 10:31:00] payments.ERROR: Webhook processing failed {"provider":"stripe","error":"Invalid signature"}
```

## Custom Log Channel

If you prefer a different channel name, you can configure it in `config/payments.php`:

```php
'logging' => [
    'enabled' => true,
    'channel' => 'custom-payments', // Use your custom channel name
    'table' => 'payment_transactions',
],
```

Then update your logging configuration accordingly.

## Disabling Logging

To disable payment logging entirely:

```php
'logging' => [
    'enabled' => false,
],
```

Note: This only disables database transaction logging. Application-level logging will still occur through Laravel's default log channel.

## Best Practices

1. **Production**: Use `daily` driver with `info` level or higher
2. **Development**: Use `single` driver with `debug` level
3. **Retention**: Set appropriate retention days based on compliance requirements
4. **Monitoring**: Integrate with log aggregation tools (e.g., Logstash, Papertrail)
5. **Security**: Ensure log files have proper permissions (not publicly accessible)

## Troubleshooting

### Logs Not Appearing

1. Check that the channel is configured in `config/logging.php`
2. Verify file permissions on `storage/logs/` directory
3. Check `LOG_LEVEL` environment variable
4. Ensure `payments.logging.enabled` is `true` in config

### Log Files Growing Too Large

1. Use `daily` driver to rotate logs automatically
2. Reduce `PAYMENTS_LOG_RETENTION_DAYS`
3. Increase log level to reduce verbosity
4. Consider using log rotation tools

