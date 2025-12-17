<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Traits;

use KenDeNigerian\PayZephyr\Constants\PaymentConstants;

/**
 * Trait providing log sanitization functionality to prevent sensitive data leakage.
 */
trait HasLogSanitization
{
    /**
     * Sensitive keys to redact from logs.
     *
     * @var array<string>
     */
    protected array $sensitiveKeys = [
        'password',
        'secret',
        'token',
        'api_key',
        'access_token',
        'refresh_token',
        'card_number',
        'cvv',
        'pin',
        'ssn',
        'account_number',
        'routing_number',
    ];

    /**
     * Recursively sanitize log context to remove sensitive information.
     *
     * @param  mixed  $data  Data to sanitize
     * @return mixed Sanitized data
     */
    protected function sanitizeLogContext(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if ($this->isSensitiveKey($key)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = $this->sanitizeLogContext($value);
                }
            }

            return $sanitized;
        }

        if (is_object($data)) {
            $array = method_exists($data, 'toArray')
                ? $data->toArray()
                : (array) $data;

            return $this->sanitizeLogContext($array);
        }

        if (is_string($data) && strlen($data) > PaymentConstants::MAX_STRING_LENGTH_FOR_TOKEN_CHECK) {
            if (preg_match('/^(sk_|pk_|whsec_|Bearer\s+)/i', $data)) {
                return '[REDACTED_TOKEN]';
            }
        }

        return $data;
    }

    /**
     * Check if a key is considered sensitive.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (str_contains($key, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }
}
