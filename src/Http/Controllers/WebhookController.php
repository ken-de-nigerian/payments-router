<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KenDeNigerian\PayZephyr\Constants\PaymentStatus;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;
use KenDeNigerian\PayZephyr\PaymentManager;
use KenDeNigerian\PayZephyr\Services\StatusNormalizer;
use Throwable;

/**
 * WebhookController - Handles Payment Notifications from Providers
 *
 * When a payment is completed (or fails), the payment provider sends a webhook
 * (a POST request) to this controller. This controller:
 * 1. Verifies the webhook is really from the provider (security check)
 * 2. Updates the payment record in database
 * 3. Fires Laravel events so your app can react (e.g., send email, update order status)
 */
class WebhookController extends Controller
{
    protected StatusNormalizer $statusNormalizer;

    public function __construct(
        protected PaymentManager $manager,
        ?StatusNormalizer $statusNormalizer = null
    ) {
        $this->statusNormalizer = $statusNormalizer ?? app(StatusNormalizer::class);
    }

    /**
     * Process an incoming webhook from a payment provider.
     *
     * This is called automatically when a provider sends a webhook.
     * It verifies the webhook is legitimate, updates your database,
     * and fires events so your app can handle the payment status change.
     *
     * @param  Request  $request  The webhook HTTP request
     * @param  string  $provider  Which provider sent it (e.g., 'paystack', 'stripe')?
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

            // Update transaction if reference exists and logging is enabled
            if ($reference && config('payments.logging.enabled', true)) {
                $this->updateTransactionFromWebhook($provider, $reference, $payload);
            }

            // Fire events even if no reference (webhook still processed)
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
     * Find the transaction reference in the webhook data.
     *
     * Each provider structures their webhook differently, so this method
     * knows where to look for the reference in each provider's format.
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
     * Figure out the payment status from the webhook data.
     *
     * Each provider uses different status names (e.g., 'successful', 'succeeded', 'PAID'),
     * so this converts them all to our standard format: 'success', 'failed', or 'pending'.
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

        return $this->statusNormalizer->normalize($status, $provider);
    }

    /**
     * Update the payment record in the database when we receive a webhook.
     *
     * Updates the payment status, which payment method was used, and when it was paid.
     */
    protected function updateTransactionFromWebhook(string $provider, string $reference, array $payload): void
    {
        try {
            $status = $this->determineStatus($provider, $payload);

            $updateData = [
                'status' => $status,
            ];

            $statusEnum = PaymentStatus::tryFromString($status);
            if ($statusEnum?->isSuccessful()) {
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
