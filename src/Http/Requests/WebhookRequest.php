<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use KenDeNigerian\PayZephyr\PaymentManager;
use Throwable;

/**
 * Webhook request validation.
 */
class WebhookRequest extends FormRequest
{
    /**
     * Authorize webhook request.
     */
    public function authorize(): bool
    {
        $config = app('payments.config') ?? config('payments', []);
        if (! ($config['webhook']['verify_signature'] ?? true)) {
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
            logger()->warning("Webhook authorization failed for provider [$provider]", [
                'error' => $e->getMessage(),
                'ip' => $this->ip(),
            ]);

            return false;
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
