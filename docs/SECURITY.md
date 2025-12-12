# Security Guide

## Overview

PayZephyr implements multiple security layers to protect your application and customer data. This guide covers all security features, best practices, and how to configure them properly.

---

## 🔒 Security Features

### 1. SQL Injection Prevention

**Protection:** Table name validation prevents SQL injection attacks through configuration manipulation.

**Implementation:**
- All table names are validated against a strict regex pattern: `/^[a-zA-Z0-9_]{1,64}$/`
- Invalid table names automatically fall back to the default table name
- Warnings are logged when invalid table names are detected

**Example:**
```php
// ❌ This will be rejected
config(['payments.logging.table' => 'payment_transactions; DROP TABLE users--']);

// ✅ This will be accepted
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

### 2. Webhook Replay Attack Prevention

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
- Supports Laravel auth, custom tenant resolvers, and session-based identification

**Cache Key Format:**
```
# With user context
payzephyr:user_123:session:REF_ABC

# Without context (webhooks, CLI)
payzephyr:session:REF_ABC
```

**Automatic Detection:**
1. Laravel authenticated user (`auth()->id()`)
2. Custom tenant resolver (`tenant()->id`)
3. Request-based user (`$request->user()->id`)
4. Session-based user (`$request->session()->get('user_id')`)

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
// ❌ Rejected
'user..name@example.com'  // Double dots
'user@.example.com'       // Dot after @
'user@example.com.'        // Trailing dot

// ✅ Accepted
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
// ❌ Rejected in production
'http://example.com/callback'

// ✅ Accepted
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
// ❌ Rejected
'ORDER_123; DROP TABLE users--'
'ORDER 123'
'ORDER@123'

// ✅ Accepted
'ORDER_123'
'ORDER-123-ABC'
'ORDER123ABC'
```

---

## 🛡️ Security Best Practices

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
# ✅ Good - Environment variables
PAYSTACK_SECRET_KEY=sk_live_xxxxx

# ❌ Bad - Hardcoded in code
$secretKey = 'sk_live_xxxxx';
```

### 3. Webhook Security

**Always validate signatures:**
```php
// ✅ Good - Validation enabled
'webhook' => [
    'verify_signature' => true,
],

// ❌ Bad - Never disable in production
'webhook' => [
    'verify_signature' => false,
],
```

**Use HTTPS endpoints:**
```php
// ✅ Good
'https://yourdomain.com/payments/webhook/paystack'

// ❌ Bad
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

### 5. Logging Security

**Never log sensitive data:**
```php
// ❌ Bad
logger()->info('Payment processed', [
    'api_key' => $apiKey,  // Will be redacted, but don't log it
    'card_number' => $cardNumber,  // Never log this
]);

// ✅ Good
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

---

**Last Updated:** 2025-01-27  
**Version:** 1.2.0

