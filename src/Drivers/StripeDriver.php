<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use Exception;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequest;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponse;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponse;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;
use Random\RandomException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Class StripeDriver
 *
 * Stripe payment gateway driver
 */
class StripeDriver extends AbstractDriver
{
    protected string $name = 'stripe';

    /**
     * @var StripeClient|object
     */
    protected $stripe;

    /**
     * Validate configuration
     *
     * @throws InvalidConfigurationException
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['secret_key'])) {
            throw new InvalidConfigurationException('Stripe secret key is required');
        }
    }

    /**
     * Initialize client
     */
    protected function initializeClient(): void
    {
        parent::initializeClient();
        $this->stripe = new StripeClient($this->config['secret_key']);
    }

    /**
     * Setter for mocking in tests
     */
    public function setStripeClient(object $stripe): void
    {
        $this->stripe = $stripe;
    }

    /**
     * Get default headers
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['secret_key'],
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Initialize a charge
     *
     * @throws ChargeException
     * @throws RandomException
     */
    public function charge(ChargeRequest $request): ChargeResponse
    {
        try {
            $reference = $request->reference ?? $this->generateReference('STRIPE');

            $params = [
                'amount' => $request->getAmountInMinorUnits(),
                'currency' => strtolower($request->currency),
                'receipt_email' => $request->email,
                'metadata' => array_merge($request->metadata, [
                    'reference' => $reference,
                    'email' => $request->email,
                ]),
                'description' => $request->description ?? 'Payment',
            ];

            if ($request->customer) {
                $params['customer'] = $request->customer['id'] ?? null;
            }

            // We treat $this->stripe as dynamic here to allow both StripeClient and Mock calls
            $intent = $this->stripe->paymentIntents->create($params);

            $this->log('info', 'Charge initialized successfully', [
                'reference' => $reference,
                'intent_id' => $intent->id,
            ]);

            // Return payment intent details for client-side confirmation
            return new ChargeResponse(
                reference: $reference,
                authorizationUrl: $intent->client_secret, // For Stripe, we use client_secret
                accessCode: $intent->id,
                status: $intent->status,
                metadata: [
                    'client_secret' => $intent->client_secret,
                    'payment_intent_id' => $intent->id,
                ],
                provider: $this->getName(),
            );
        } catch (ApiErrorException $e) {
            $this->log('error', 'Charge failed', ['error' => $e->getMessage()]);
            throw new ChargeException('Stripe charge failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify a payment
     *
     * @throws VerificationException
     */
    public function verify(string $reference): VerificationResponse
    {
        try {
            // Reference could be payment_intent_id or our custom reference
            // Try to retrieve as payment intent first
            try {
                $intent = $this->stripe->paymentIntents->retrieve($reference);
            } catch (ApiErrorException) {
                // If not found, search by metadata
                $intents = $this->stripe->paymentIntents->all([
                    'limit' => 1,
                ])->data;

                $intent = null;
                foreach ($intents as $pi) {
                    if (($pi->metadata['reference'] ?? '') === $reference) {
                        $intent = $pi;
                        break;
                    }
                }

                if (! $intent) {
                    throw new VerificationException('Payment intent not found');
                }
            }

            $this->log('info', 'Payment verified', [
                'reference' => $reference,
                'status' => $intent->status,
            ]);

            return new VerificationResponse(
                reference: $intent->metadata['reference'] ?? $intent->id,
                status: $this->normalizeStatus($intent->status),
                amount: $intent->amount / 100,
                currency: strtoupper($intent->currency),
                paidAt: $intent->status === 'succeeded' ? date('Y-m-d H:i:s', $intent->created) : null,
                metadata: (array) $intent->metadata,
                provider: $this->getName(),
                channel: $intent->payment_method ?? null,
                customer: [
                    'email' => $intent->receipt_email,
                ],
            );
        } catch (ApiErrorException $e) {
            $this->log('error', 'Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new VerificationException('Stripe verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['stripe-signature'][0]
            ?? $headers['Stripe-Signature'][0]
            ?? null;

        if (! $signature || empty($this->config['webhook_secret'])) {
            $this->log('warning', 'Webhook signature or secret missing');

            return false;
        }

        try {
            Webhook::constructEvent(
                $body,
                $signature,
                $this->config['webhook_secret']
            );

            $this->log('info', 'Webhook validated successfully');

            return true;
        } catch (Exception $e) {
            $this->log('warning', 'Webhook validation failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Health check
     */
    public function healthCheck(): bool
    {
        try {
            // Simple API call to check connectivity
            $this->stripe->balance->retrieve();

            return true;
        } catch (ApiErrorException $e) {
            $this->log('error', 'Health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Normalize status from Stripe to standard format
     */
    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => 'success',
            'processing' => 'pending',
            'requires_payment_method', 'requires_confirmation', 'requires_action' => 'pending',
            'canceled' => 'cancelled',
            default => $status,
        };
    }
}
