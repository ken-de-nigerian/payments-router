<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;
use KenDeNigerian\PayZephyr\PaymentManager;
use Throwable;

/**
 * Handles incoming webhooks from various payment providers.
 *
 * This controller is responsible for verifying signatures, parsing payloads,
 * updating local transaction records, and dispatching events for the
 * host application to consume.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected PaymentManager $manager
    ) {}

    /**
     * Handle the incoming webhook request.
     *
     * Validates the webhook signature (if enabled), updates the database,
     * and dispatches Laravel events.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            $driver = $this->manager->driver($provider);
            $rawBody = $request->getContent();

            if (config('payments.webhook.verify_signature', true)) {
                $isValid = $driver->validateWebhook(
                    $request->headers->all(),
                    $rawBody
                );

                if (! $isValid) {
                    logger()->warning("Invalid webhook signature for $provider", [
                        'ip' => $request->ip(),
                        'headers' => $request->headers->all(),
                    ]);

                    return response()->json(['error' => 'Invalid signature'], 403);
                }
            }

            $payload = $request->all();
            $reference = $this->extractReference($provider, $payload);

            if ($reference && config('payments.logging.enabled', true)) {
                $this->updateTransactionFromWebhook($provider, $reference, $payload);
            }

            event("payments.webhook.$provider", [$payload]);
            event('payments.webhook', [$provider, $payload]);

            logger()->info("Webhook processed for $provider", [
                'reference' => $reference,
                'event' => $payload['event'] ?? $payload['eventType'] ?? $payload['event_type'] ?? 'unknown',
            ]);

            return response()->json(['status' => 'success']);
        } catch (Throwable $e) {
            logger()->error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Webhook received but processing failed internally'], 500);
        }
    }

    /**
     * Parse the provider-specific payload to find the unique transaction reference.
     */
    protected function extractReference(string $provider, array $payload): ?string
    {
        return match ($provider) {
            'paystack' => $payload['data']['reference'] ?? null,
            'flutterwave' => $payload['data']['tx_ref'] ?? null,
            'monnify' => $payload['paymentReference'] ?? $payload['transactionReference'] ?? null,
            'stripe' => $payload['data']['object']['metadata']['reference'] ??
                $payload['data']['object']['client_reference_id'] ?? null,
            'paypal' => $payload['resource']['custom_id'] ??
                $payload['resource']['purchase_units'][0]['custom_id'] ?? null,
            default => null,
        };
    }

    /**
     * Parse the provider-specific payload to find the status.
     */
    protected function determineStatus(string $provider, array $payload): string
    {
        $status = match ($provider) {
            'paystack' => $payload['data']['status'] ?? 'unknown',
            'flutterwave' => $payload['data']['status'] ?? 'unknown',
            'monnify' => $payload['paymentStatus'] ?? 'unknown',
            'stripe' => $payload['data']['object']['status'] ??
                $payload['type'] ?? 'unknown',
            'paypal' => $payload['resource']['status'] ??
                $payload['event_type'] ?? 'unknown',
            default => 'unknown',
        };

        return $this->normalizeStatus($status);
    }

    /**
     * Map provider specific statuses to a unified set of internal statuses.
     *
     * Returns: success, failed, pending, or the original status string.
     */
    protected function normalizeStatus(string $status): string
    {
        $status = strtolower($status);

        if (in_array($status, ['success', 'succeeded', 'completed', 'successful', 'payment.capture.completed', 'paid'])) {
            return 'success';
        }

        if (in_array($status, ['failed', 'cancelled', 'declined', 'payment.capture.denied'])) {
            return 'failed';
        }

        if (in_array($status, ['pending', 'processing', 'requires_action', 'requires_payment_method'])) {
            return 'pending';
        }

        return $status;
    }

    /**
     * Update the local database record based on the webhook data.
     *
     * This captures the status change, channel information, and payment timestamp.
     */
    protected function updateTransactionFromWebhook(string $provider, string $reference, array $payload): void
    {
        try {
            $status = $this->determineStatus($provider, $payload);

            $updateData = [
                'status' => $status,
            ];

            if ($status === 'success') {
                $updateData['paid_at'] = now();
            }

            $channel = match ($provider) {
                'paystack' => $payload['data']['channel'] ?? null,
                'flutterwave' => $payload['data']['payment_type'] ?? null,
                'monnify' => $payload['paymentMethod'] ?? null,
                'stripe' => $payload['data']['object']['payment_method'] ?? null,
                'paypal' => 'paypal',
                default => null,
            };

            if ($channel) {
                $updateData['channel'] = $channel;
            }

            PaymentTransaction::where('reference', $reference)->update($updateData);

            logger()->info('Transaction updated from webhook', [
                'reference' => $reference,
                'status' => $status,
                'provider' => $provider,
            ]);
        } catch (Throwable $e) {
            logger()->error('Failed to update transaction from webhook', [
                'error' => $e->getMessage(),
                'reference' => $reference,
                'provider' => $provider,
            ]);
        }
    }
}
