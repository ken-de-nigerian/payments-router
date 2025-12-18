<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Traits;

use KenDeNigerian\PayZephyr\Constants\PaymentConstants;

/**
 * Trait providing webhook validation functionality.
 */
trait HasWebhookValidation
{
    /**
     * Validate webhook timestamp to prevent replay attacks.
     *
     * @param  array<string, mixed>  $payload  Webhook payload
     * @param  int  $toleranceSeconds  Allowed time difference (default: 300 = 5 minutes)
     */
    protected function validateWebhookTimestamp(array $payload, int $toleranceSeconds = PaymentConstants::WEBHOOK_TIMESTAMP_TOLERANCE_SECONDS): bool
    {
        $timestamp = $this->extractWebhookTimestamp($payload);

        if ($timestamp === null) {
            $this->log('warning', 'Webhook timestamp missing', [
                'hint' => 'Consider rejecting webhooks without timestamps to prevent replay attacks',
            ]);

            return true;
        }

        $currentTime = time();
        $timeDifference = abs($currentTime - $timestamp);

        if ($timeDifference > $toleranceSeconds) {
            $this->log('warning', 'Webhook timestamp outside tolerance window', [
                'timestamp' => $timestamp,
                'current_time' => $currentTime,
                'difference_seconds' => $timeDifference,
                'tolerance_seconds' => $toleranceSeconds,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Extract timestamp from webhook payload.
     * Override in specific drivers if needed.
     *
     * @param  array<string, mixed>  $payload
     * @return int|null Unix timestamp
     */
    protected function extractWebhookTimestamp(array $payload): ?int
    {
        $timestampFields = [
            'timestamp',
            'created_at',
            'createdAt',
            'event_time',
            'eventTime',
            'time',
        ];

        foreach ($timestampFields as $field) {
            if (isset($payload[$field])) {
                $value = $payload[$field];

                if (is_string($value) && strtotime($value) !== false) {
                    return strtotime($value);
                }

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        }

        return null;
    }
}
