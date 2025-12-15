# Security Guide

## Overview

PayZephyr implements multiple security layers to protect your application and customer data. This guide covers all security features, best practices, and how to configure them properly.

---

## Security Features

### 1. SQL Injection Prevention

**Protection:** Table name validation prevents SQL injection attacks through configuration manipulation.

**Implementation:**
- All table names are validated against a strict regex pattern: `/^[a-zA-Z0-9_]{1,64}$/`
- Invalid table names automatically fall back to the default table name
- Warnings are logged when invalid table names are detected

**Example:**
```php
// This will be rejected
config(['payments.logging.table' => 'payment_transactions; DROP TABLE users--']);

// This will be accepted
config(['payments.logging.table' => 'custom_payment_transactions']);
```

**Configuration:**
```php
// config/payments.php
'logging' => [
    'table' => env('PAYMENTS_TABLE_NAME', 'payment_transactions'),
],
```

---

### 2. Metadata Sanitization

**Protection:** Automatic XSS prevention for metadata and customer data before database storage.

**Implementation:**
- All metadata and customer data is sanitized before storage
- HTML tags are stripped and special characters are escaped
- Dangerous patterns (JavaScript, VBScript) are removed
- Array size and depth limits prevent DoS attacks
- Key validation ensures only safe characters are used

**Configuration:**
```php
// Automatically enabled - no configuration needed
// Sanitization happens transparently in PaymentManager
```

**What Gets Sanitized:**
- Payment metadata arrays
- Customer information objects
- All string values in nested arrays

### 3. Health Endpoint Security

**What it does:** The `/payments/health` endpoint checks if your payment providers are working. It returns the health status and supported currencies for each enabled provider.

**Why secure it:** The endpoint exposes provider names and currencies. In production, you should restrict access to prevent information disclosure.

**Protection:** IP whitelisting and token authentication.

**Configuration:**

```env
# Require authentication (forces token or IP check)
PAYMENTS_HEALTH_CHECK_REQUIRE_AUTH=true

# IP whitelist (comma-separated)
# Supports single IPs: 127.0.0.1
# Supports CIDR notation: 10.0.0.0/8 (entire 10.x.x.x range)
PAYMENTS_HEALTH_CHECK_ALLOWED_IPS=127.0.0.1,192.168.1.100,10.0.0.0/8

# Token authentication (comma-separated, multiple tokens allowed)
# Generate your own secure tokens (see below)
PAYMENTS_HEALTH_CHECK_ALLOWED_TOKENS=your-secret-token-1,your-secret-token-2

# Cache duration in seconds (default: 300 = 5 minutes)
PAYMENTS_HEALTH_CHECK_CACHE_TTL=300
```

**Generating Tokens:**

You need to generate your own secure tokens. Here are several ways:

**Option 1: Using Laravel (Recommended)**
```bash
php artisan tinker
```
```php
Str::random(32) // Generates: "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
```

**Option 2: Using OpenSSL**
```bash
openssl rand -hex 32
```

**Option 3: Using PHP**
```php
bin2hex(random_bytes(32))
```

**Option 4: Using Online Generator**
Use a secure random string generator (at least 32 characters).

**Example Setup:**
```bash
# Generate a token
php artisan tinker
>>> Str::random(32)
=> "xK9mP2qR7vT4wY8zA1bC3dE5fG6hI0j"

# Add to .env
PAYMENTS_HEALTH_CHECK_ALLOWED_TOKENS=xK9mP2qR7vT4wY8zA1bC3dE5fG6hI0j,another-token-here

# Clear config cache
php artisan config:clear
```

**How to use:**

**1. With Bearer Token (Recommended):**
```bash
# Replace 'your-secret-token-1' with the token you generated
curl -H "Authorization: Bearer xK9mP2qR7vT4wY8zA1bC3dE5fG6hI0j" https://yourdomain.com/payments/health
```

**2. With Custom Header:**
```bash
curl -H "X-Health-Token: xK9mP2qR7vT4wY8zA1bC3dE5fG6hI0j" https://yourdomain.com/payments/health
```

**3. With Query Parameter:**
```bash
curl https://yourdomain.com/payments/health?token=xK9mP2qR7vT4wY8zA1bC3dE5fG6hI0j
```

**4. From IP Whitelist (No token needed):**
```bash
# If your IP is in ALLOWED_IPS, no token required
curl https://yourdomain.com/payments/health
```

**Response Format:**
```json
{
  "status": "operational",
  "providers": {
    "paystack": {
      "healthy": true,
      "currencies": ["NGN", "USD", "GHS", "ZAR"]
    },
    "stripe": {
      "healthy": true,
      "currencies": ["USD", "EUR", "GBP", "CAD", "AUD"]
    },
    "flutterwave": {
      "healthy": false,
      "error": "Connection timeout"
    }
  }
}
```

**Programmatic Access:**

```php
// Check health from your application
// Use the token you generated and added to .env
$token = config('payments.health_check.allowed_tokens')[0] ?? 'your-token';
$response = Http::withToken($token)
    ->get(url('/payments/health'));

$data = $response->json();

if ($data['providers']['paystack']['healthy']) {
    // Paystack is available - proceed with payment
} else {
    // Use fallback provider
}

// Check individual provider programmatically
$manager = app(PaymentManager::class);
$driver = $manager->driver('paystack');

// Cached check (recommended - uses cache)
$isHealthy = $driver->getCachedHealthCheck();

// Direct check (bypasses cache - use sparingly)
$isHealthy = $driver->healthCheck();
```

**Use Cases:**
- Monitor provider availability before processing payments
- Set up uptime monitoring (UptimeRobot, Pingdom, StatusCake)
- Check provider health in your dashboard
- Automatically switch to backup providers when primary is down

**Security Best Practices:**
- Always enable authentication in production
- Use strong, random tokens
- Rotate tokens periodically
- Restrict IP access to your monitoring services
- Use HTTPS only

### 4. Webhook Payload Size Limits

**Protection:** Prevents DoS attacks via large webhook payloads.

**Configuration:**
```env
# Maximum payload size in bytes (default: 1MB)
PAYMENTS_WEBHOOK_MAX_PAYLOAD_SIZE=1048576
```

**Behavior:**
- Payloads exceeding the limit are automatically rejected
- Warnings are logged for oversized payloads
- Default limit: 1MB (1,048,576 bytes)

### 5. Webhook Replay Attack Prevention

**Protection:** Timestamp validation prevents attackers from replaying old webhook payloads.

**Implementation:**
- Webhooks are validated for timestamp freshness (default: 5 minutes tolerance)
- Old webhooks outside the tolerance window are automatically rejected
- Configurable tolerance window via environment variable

**How it works:**
1. Webhook payload is checked for timestamp fields (`timestamp`, `created_at`, `event_time`, etc.)
2. Timestamp is compared against current server time
3. If difference exceeds tolerance, webhook is rejected as potential replay attack

**Configuration:**
```env
# 5 minutes (300 seconds) - default
PAYMENTS_WEBHOOK_TIMESTAMP_TOLERANCE=300

# 10 minutes
PAYMENTS_WEBHOOK_TIMESTAMP_TOLERANCE=600
```

**Backward Compatibility:**
- Webhooks without timestamps are still accepted (with a warning logged)
- This ensures existing integrations continue to work

---

### 3. Multi-Tenant Cache Isolation

**Protection:** Prevents cache poisoning attacks in multi-tenant applications.

**Implementation:**
- Cache keys automatically include user/tenant context
- Each user's payment sessions are isolated
- Supports Laravel auth and session-based identification

**Cache Key Format:**
```
# With user context
payzephyr:user_123:session:REF_ABC

# Without context (webhooks, CLI)
payzephyr:session:REF_ABC
```

**Automatic Detection:**
1. Laravel authenticated user (`auth()->id()`)
2. Request-based user (`$request->user()->id`)
3. Session-based user (`$request->session()->get('user_id')`)

**Configuration:**
```env
# Enable/disable cache isolation
PAYMENTS_CACHE_ISOLATION=true
```

---

### 4. Log Sanitization

**Protection:** Prevents sensitive data exposure in application logs.

**Implementation:**
- Automatic redaction of sensitive keys (password, secret, token, api_key, etc.)
- Pattern-based detection of API keys and tokens
- Recursive sanitization of nested arrays and objects

**Sensitive Keys Detected:**
- `password`, `secret`, `token`, `api_key`, `access_token`
- `refresh_token`, `card_number`, `cvv`, `pin`, `ssn`
- `account_number`, `routing_number`
- Any key containing these words (case-insensitive)

**Token Patterns:**
- Stripe keys: `sk_`, `pk_`, `whsec_`
- Bearer tokens: `Bearer ` prefix
- Any string > 20 characters matching these patterns

**Example:**
```php
// Before sanitization
[
    'api_key' => 'sk_test_1234567890',
    'password' => 'secret123',
    'email' => 'user@example.com',
]

// After sanitization (in logs)
[
    'api_key' => '[REDACTED]',
    'password' => '[REDACTED]',
    'email' => 'user@example.com', // Not redacted
]
```

**Configuration:**
```env
# Enable/disable log sanitization
PAYMENTS_SANITIZE_LOGS=true
```

---

### 5. Rate Limiting

**Protection:** Prevents payment spam and DoS attacks.

**Implementation:**
- Automatic rate limiting on payment initialization
- Per-user, per-email, or per-IP rate limiting
- Configurable limits and decay windows

**Rate Limit Keys:**
- Authenticated users: `payment_charge:user_{id}`
- Guest users (by email): `payment_charge:email_{hash}`
- Fallback (by IP): `payment_charge:ip_{ip_address}`
- Global fallback: `payment_charge:global`

**Default Limits:**
- Max attempts: 10 per minute
- Decay window: 60 seconds

**Configuration:**
```env
# Enable/disable rate limiting
PAYMENTS_RATE_LIMIT_ENABLED=true

# Max attempts per window
PAYMENTS_RATE_LIMIT_ATTEMPTS=10

# Decay window in seconds
PAYMENTS_RATE_LIMIT_DECAY=60
```

**Error Message:**
```
Too many payment attempts. Please try again in {seconds} seconds.
```

---

### 6. Enhanced Input Validation

**Protection:** Prevents injection attacks and malformed data.

#### Email Validation

**Checks:**
- RFC 5322 compliant email format
- Local part length (max 64 characters)
- Domain validation
- Suspicious patterns:
  - Double dots (`..`)
  - Dot right after `@` (`@.`)
  - Trailing/leading dots

**Example:**
```php
// Rejected
'user..name@example.com'  // Double dots
'user@.example.com'       // Dot after @
'user@example.com.'        // Trailing dot

// Accepted
'user.name@example.com'
'user+tag@example.com'
```

#### URL Validation

**Checks:**
- Valid URL format
- HTTPS enforcement in production
- HTTP allowed in development/testing

**Example:**
```php
// Rejected in production
'http://example.com/callback'

// Accepted
'https://example.com/callback'
```

#### Reference Validation

**Checks:**
- Alphanumeric, underscore, hyphen only
- Max 255 characters
- No special characters that could cause SQL injection

**Pattern:** `/^[a-zA-Z0-9_-]{1,255}$/`

**Example:**
```php
// Rejected
'ORDER_123; DROP TABLE users--'
'ORDER 123'
'ORDER@123'

// Accepted
'ORDER_123'
'ORDER-123-ABC'
'ORDER123ABC'
```

---

## Security Best Practices

### 1. Environment Variables

**Never commit `.env` files:**
```bash
# .gitignore
.env
.env.local
.env.*.local
```

**Use secure storage:**
- Production: Use Laravel's encrypted environment variables
- CI/CD: Use secret management (GitHub Secrets, GitLab CI Variables, etc.)

### 2. API Keys

**Rotate keys regularly:**
- Change API keys every 90 days
- Immediately rotate if compromised
- Use different keys for staging/production

**Key Storage:**
```env
# Good - Environment variables
PAYSTACK_SECRET_KEY=sk_live_xxxxx

# Bad - Hardcoded in code
$secretKey = 'sk_live_xxxxx';
```

### 3. Webhook Security

**Always validate signatures:**
```php
// Good - Validation enabled
'webhook' => [
    'verify_signature' => true,
],

// Bad - Never disable in production
'webhook' => [
    'verify_signature' => false,
],
```

**Use HTTPS endpoints:**
```php
// Good
'https://yourdomain.com/payments/webhook/paystack'

// Bad
'http://yourdomain.com/payments/webhook/paystack'
```

**Monitor webhook logs:**
- Check for failed signature validations
- Monitor timestamp validation failures
- Alert on suspicious patterns

### 4. Database Security

**Use parameterized queries:**
- PayZephyr uses Eloquent ORM (automatically parameterized)
- Never use raw queries with user input

**Table name validation:**
- Only use alphanumeric and underscore characters
- Max 64 characters
- No special characters

**Race Condition Protection:**
- Transaction updates use database row locking (`lockForUpdate()`)
- Status is re-checked after lock acquisition to prevent race conditions
- Ensures concurrent webhook/verification requests don't cause duplicate processing
- Protects against simultaneous updates from multiple sources (webhooks, callbacks, manual verification)

### 5. Logging Security

**Never log sensitive data:**
```php
// Bad
logger()->info('Payment processed', [
    'api_key' => $apiKey,  // Will be redacted, but don't log it
    'card_number' => $cardNumber,  // Never log this
]);

// Good
logger()->info('Payment processed', [
    'reference' => $reference,
    'amount' => $amount,
    'status' => $status,
]);
```

**Log sanitization is automatic, but:**
- Don't rely on it as your only protection
- Never intentionally log sensitive data
- Review logs before sharing/debugging

### 6. Rate Limiting

**Adjust limits based on use case:**
```env
# High-volume e-commerce
PAYMENTS_RATE_LIMIT_ATTEMPTS=20
PAYMENTS_RATE_LIMIT_DECAY=60

# Low-volume SaaS
PAYMENTS_RATE_LIMIT_ATTEMPTS=5
PAYMENTS_RATE_LIMIT_DECAY=300
```

**Monitor rate limit hits:**
- Alert on excessive rate limiting
- May indicate attack or UX issue
- Adjust limits based on legitimate traffic

---

## 🔍 Security Monitoring

### 1. Log Monitoring

**Key indicators to monitor:**
- Invalid table name warnings
- Webhook timestamp validation failures
- Rate limit exceeded errors
- Failed signature validations

**Example monitoring:**
- Check Laravel logs for security warnings
- Look for entries containing "warning", "security", or "invalid"
- Use your log monitoring tool (e.g., Laravel Log Viewer, Papertrail, CloudWatch) to filter logs

### 2. Webhook Monitoring

**Monitor webhook health:**
- Signature validation success rate
- Timestamp validation failures
- Replay attack attempts

**Set up alerts for:**
- High rate of failed validations
- Timestamp validation failures
- Unusual webhook patterns

### 3. Rate Limit Monitoring

**Track rate limit hits:**
```php
// In your monitoring system
RateLimiter::attempts('payment_charge:user_123');
RateLimiter::availableIn('payment_charge:user_123');
```

**Alert on:**
- Sustained rate limit hits from single user/IP
- Unusual patterns (may indicate attack)

---

## 🚨 Incident Response

### If API Keys Are Compromised

1. **Immediately rotate keys:**
   ```bash
   # Generate new keys from provider dashboard
   # Update .env file
   # Restart application
   ```

2. **Review recent transactions:**
   ```php
   PaymentTransaction::where('created_at', '>=', $compromisedTime)
       ->get();
   ```

3. **Check logs for suspicious activity:**
   - Review Laravel logs for errors, failures, or unauthorized access attempts
   - Use your log monitoring tool to search for security-related keywords
   - Check for unusual patterns or repeated failures

4. **Notify affected customers** (if applicable)

### If Webhook Security Is Breached

1. **Verify webhook endpoint security:**
   - Check signature validation is enabled
   - Verify timestamp tolerance is appropriate
   - Review recent webhook logs

2. **Temporarily increase timestamp tolerance** (if needed):
   ```env
   PAYMENTS_WEBHOOK_TIMESTAMP_TOLERANCE=60  # 1 minute
   ```

3. **Review processed webhooks:**
   - Check for duplicate processing
   - Verify transaction statuses
   - Look for suspicious patterns

---

## 📋 Security Checklist

### Before Going to Production

- [ ] All API keys are production keys (not test/sandbox)
- [ ] Callback URLs use HTTPS
- [ ] Webhook signature validation is enabled
- [ ] Rate limiting is configured appropriately
- [ ] Log sanitization is enabled
- [ ] Cache isolation is enabled
- [ ] Environment variables are secure
- [ ] Database backups are scheduled
- [ ] Error notifications are set up
- [ ] Security monitoring is configured

### Regular Security Maintenance

- [ ] Rotate API keys every 90 days
- [ ] Review security logs weekly
- [ ] Update dependencies monthly
- [ ] Review rate limit settings quarterly
- [ ] Audit webhook security annually
- [ ] Test incident response procedures

---

## 🔗 Additional Resources

- [Laravel Security Documentation](https://laravel.com/docs/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Payment Card Industry (PCI) Compliance](https://www.pcisecuritystandards.org/)

---

## 📞 Security Reporting

If you discover a security vulnerability, please report it responsibly:

1. **Do not** open a public GitHub issue
2. Email security concerns to: **ken.de.nigerian@payzephyr.dev**
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

We take security seriously and will respond promptly to all reports.

