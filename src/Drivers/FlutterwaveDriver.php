<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use GuzzleHttp\Exception\GuzzleException;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequest;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponse;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponse;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;
use Random\RandomException;

/**
 * Class FlutterwaveDriver
 *
 * Flutterwave payment gateway driver
 */
class FlutterwaveDriver extends AbstractDriver
{
    protected string $name = 'flutterwave';

    /**
     * Validate configuration
     *
     * @throws InvalidConfigurationException
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['secret_key'])) {
            throw new InvalidConfigurationException('Flutterwave secret key is required');
        }
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
            $reference = $request->reference ?? $this->generateReference('FLW');

            $payload = [
                'tx_ref' => $reference,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'redirect_url' => $request->callbackUrl ?? $this->config['callback_url'],
                'customer' => [
                    'email' => $request->email,
                    'name' => $request->customer['name'] ?? 'Customer',
                ],
                'customizations' => [
                    'title' => $request->description ?? 'Payment',
                    'description' => $request->description ?? 'Payment for services',
                ],
                'meta' => $request->metadata,
            ];

            $response = $this->makeRequest('POST', '/payments', [
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            if (($data['status'] ?? '') !== 'success') {
                throw new ChargeException(
                    $data['message'] ?? 'Failed to initialize Flutterwave transaction'
                );
            }

            $result = $data['data'];

            $this->log('info', 'Charge initialized successfully', [
                'reference' => $reference,
            ]);

            return new ChargeResponse(
                reference: $reference,
                authorizationUrl: $result['link'],
                accessCode: $reference,
                status: 'pending',
                metadata: $request->metadata,
                provider: $this->getName(),
            );
        } catch (GuzzleException $e) {
            $this->log('error', 'Charge failed', ['error' => $e->getMessage()]);
            throw new ChargeException('Flutterwave charge failed: '.$e->getMessage(), 0, $e);
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
            // For Flutterwave, we need the transaction ID, not just the reference
            // First, try to verify using the reference as tx_ref
            $response = $this->makeRequest('GET', '/transactions/verify_by_reference', [
                'query' => ['tx_ref' => $reference],
            ]);

            $data = $this->parseResponse($response);

            if (($data['status'] ?? '') !== 'success') {
                throw new VerificationException(
                    $data['message'] ?? 'Failed to verify Flutterwave transaction'
                );
            }

            $result = $data['data'];

            $this->log('info', 'Payment verified', [
                'reference' => $reference,
                'status' => $result['status'],
            ]);

            return new VerificationResponse(
                reference: $result['tx_ref'],
                status: $this->normalizeStatus($result['status']),
                amount: (float) $result['amount'],
                currency: $result['currency'],
                paidAt: $result['created_at'] ?? null,
                metadata: $result['meta'] ?? [],
                provider: $this->getName(),
                channel: $result['payment_type'] ?? null,
                cardType: $result['card']['type'] ?? null,
                bank: $result['card']['issuer'] ?? null,
                customer: [
                    'email' => $result['customer']['email'] ?? null,
                    'name' => $result['customer']['name'] ?? null,
                ],
            );
        } catch (GuzzleException $e) {
            $this->log('error', 'Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new VerificationException('Flutterwave verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['verif-hash'][0]
            ?? $headers['Verif-Hash'][0]
            ?? null;

        if (! $signature) {
            $this->log('warning', 'Webhook signature missing');

            return false;
        }

        $secretHash = $this->config['webhook_secret'] ?? $this->config['secret_key'];

        $isValid = hash_equals($signature, $secretHash);

        $this->log($isValid ? 'info' : 'warning', 'Webhook validation', [
            'valid' => $isValid,
        ]);

        return $isValid;
    }

    /**
     * Health check
     */
    public function healthCheck(): bool
    {
        try {
            // Simple ping to banks endpoint
            $response = $this->makeRequest('GET', '/banks/NG');

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            $this->log('error', 'Health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Normalize status from Flutterwave to standard format
     */
    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'successful' => 'success',
            'failed' => 'failed',
            'pending' => 'pending',
            default => $status,
        };
    }
}
