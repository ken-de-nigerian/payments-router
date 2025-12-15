<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use KenDeNigerian\PayZephyr\PaymentManager;
use Throwable;

class WebhookRequest extends FormRequest
{
    /**
     * Authorize webhook request.
     */
    public function authorize(): bool
    {
        $config = app('payments.config') ?? config('payments', []);
        $webhookConfig = $config['webhook'] ?? [];

        $maxPayloadSize = $webhookConfig['max_payload_size'] ?? 1048576;
        $contentLength = $this->header('Content-Length');
        $bodySize = strlen($this->getContent());

        if ($contentLength && (int) $contentLength > $maxPayloadSize) {
            $this->log('warning', 'Webhook payload size exceeds limit', [
                'size' => $contentLength,
                'max' => $maxPayloadSize,
                'ip' => $this->ip(),
            ]);

            return false;
        }

        if ($bodySize > $maxPayloadSize) {
            $this->log('warning', 'Webhook payload size exceeds limit', [
                'size' => $bodySize,
                'max' => $maxPayloadSize,
                'ip' => $this->ip(),
            ]);

            return false;
        }

        if (! ($webhookConfig['verify_signature'] ?? true)) {
            return true;
        }

        $provider = $this->route('provider');

        try {
            $manager = app(PaymentManager::class);
            $driver = $manager->driver($provider);

            return $driver->validateWebhook(
                $this->headers->all(),
                $this->getContent()
            );
        } catch (Throwable $e) {
            $this->log('warning', "Webhook authorization failed for provider [$provider]", [
                'error' => $e->getMessage(),
                'ip' => $this->ip(),
            ]);

            return false;
        }
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        $config = app('payments.config') ?? config('payments', []);
        $channelName = $config['logging']['channel'] ?? 'payments';

        try {
            Log::channel($channelName)->{$level}($message, $context);
        } catch (\InvalidArgumentException) {
            Log::{$level}($message, $context);
        }
    }

    /**
     * Get validation rules.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'event' => 'sometimes|string',
            'eventType' => 'sometimes|string',
            'event_type' => 'sometimes|string',
            'data' => 'sometimes|array',
            'reference' => 'sometimes|string',
            'status' => 'sometimes|string',
            'paymentStatus' => 'sometimes|string',
            'payment_status' => 'sometimes|string',
        ];
    }
}
