<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;
use Random\RandomException;

/**
 * OPayDriver - Handles Payments via OPay
 *
 * This driver processes payments through OPay's API.
 * OPay uses RSA authentication and provides payment processing services.
 * When you initialize a payment, it redirects the customer to OPay's
 * hosted checkout page where they can pay with card, bank transfer, etc.
 */
final class OPayDriver extends AbstractDriver
{
    protected string $name = 'opay';

    /**
     * Make sure all required OPay credentials are configured.
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['merchant_id'])) {
            throw new InvalidConfigurationException('OPay merchant ID is required');
        }
        if (empty($this->config['public_key'])) {
            throw new InvalidConfigurationException('OPay public key is required');
        }
    }

    /**
     * Get the HTTP headers needed for OPay API requests.
     *
     * Note: These headers are used for the Create Payment API.
     * The Status API requires different authentication (HMAC-SHA512 signature)
     * and should override these headers in the verify() method.
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->config['public_key'],
            'MerchantId' => $this->config['merchant_id'],
        ];
    }

    /**
     * OPay uses 'Idempotency-Key' header.
     */
    protected function getIdempotencyHeader(string $key): array
    {
        return ['Idempotency-Key' => $key];
    }

    /**
     * Create a new payment on OPay.
     *
     * Important: OPay requires amounts in the smallest currency unit
     * (kobo for NGN). This method automatically converts your amount.
     *
     * @throws ChargeException If the payment creation fails.
     * @throws RandomException If reference generation fails.
     */
    public function charge(ChargeRequestDTO $request): ChargeResponseDTO
    {
        $this->setCurrentRequest($request);

        try {
            $reference = $request->reference ?? $this->generateReference('OPAY');
            $amount = $request->getAmountInMinorUnits();

            $payload = [
                'reference' => $reference,
                'amount' => (string) $amount,
                'currency' => $request->currency,
                'callbackUrl' => $request->callbackUrl,
                'returnUrl' => $request->callbackUrl,
                'description' => $request->metadata['description'] ?? 'Payment for '.$reference,
                'customerEmail' => $request->email,
                'customerName' => $request->metadata['name'] ?? $request->email,
            ];

            // Remove null values
            $payload = array_filter($payload, fn ($value) => $value !== null);

            $response = $this->makeRequest('POST', '/api/v1/international/cashier/create', [
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            // OPay returns code: '00000' = success, others = failed
            if (($data['code'] ?? '') !== '00000') {
                throw new ChargeException(
                    $data['message'] ?? $data['msg'] ?? 'Failed to initialize OPay payment'
                );
            }

            $result = $data['data'] ?? $data;

            $this->log('info', 'Charge initialized successfully', [
                'reference' => $reference,
            ]);

            return new ChargeResponseDTO(
                reference: $reference,
                authorizationUrl: $result['cashierUrl'] ?? $result['paymentUrl'] ?? $result['checkoutUrl'],
                accessCode: $result['orderNo'] ?? $result['orderNumber'] ?? $reference,
                status: 'pending',
                metadata: $request->metadata,
                provider: $this->getName(),
            );
        } catch (GuzzleException $e) {
            $userMessage = $this->getNetworkErrorMessage($e);
            $this->log('error', 'Charge failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            throw new ChargeException($userMessage, 0, $e);
        } finally {
            $this->clearCurrentRequest();
        }
    }

    /**
     * Verify an OPay payment by transaction reference.
     *
     * Looks up the transaction by reference and returns the payment details.
     * The amount is automatically converted back from kobo to main units.
     *
     * Note: The status API requires HMAC-SHA512 signature authentication,
     * unlike the create payment API which uses public key authentication.
     *
     * @param  string  $reference  The transaction reference
     *
     * @throws VerificationException|InvalidConfigurationException If the payment can't be found or verified.
     */
    public function verify(string $reference): VerificationResponseDTO
    {
        try {
            $payload = ['reference' => $reference];
            $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

            $privateKey = $this->config['secret_key'] ?? null;
            if (empty($privateKey)) {
                throw new InvalidConfigurationException('OPay secret key (private key) is required for status API authentication');
            }

            $hmacKey = $privateKey.$this->config['merchant_id'];
            $signature = hash_hmac('sha512', $payloadJson, $hmacKey);

            // Status API requires signature-based authentication
            $response = $this->makeRequest('POST', '/api/v1/international/cashier/status', [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer '.$signature,
                    'MerchantId' => $this->config['merchant_id'],
                ],
            ]);

            $data = $this->parseResponse($response);

            // Check if response indicates success
            if (($data['code'] ?? '') !== '00000') {
                throw new VerificationException(
                    $data['message'] ?? $data['msg'] ?? 'Failed to verify OPay transaction'
                );
            }

            $result = $data['data'] ?? $data;

            $this->log('info', 'Payment verified', [
                'reference' => $reference,
                'status' => $result['status'] ?? $result['orderStatus'] ?? 'unknown',
            ]);

            $opayStatus = $result['status'] ?? $result['orderStatus'] ?? 'unknown';
            $status = match (strtoupper($opayStatus)) {
                'SUCCESS', 'SUCCEEDED', 'PAID' => 'success',
                'PENDING', 'PROCESSING' => 'pending',
                default => $this->normalizeStatus($opayStatus),
            };

            return new VerificationResponseDTO(
                reference: $result['reference'] ?? $result['orderNo'] ?? $reference,
                status: $status,
                amount: ($result['amount'] ?? 0) / 100,
                currency: $result['currency'] ?? 'NGN',
                paidAt: $result['payTime'] ?? $result['paymentDate'] ?? $result['transactionDate'] ?? null,
                metadata: $result['metadata'] ?? [],
                provider: $this->getName(),
                channel: $result['paymentChannel'] ?? $result['channel'] ?? null,
                cardType: $result['cardType'] ?? null,
                customer: [
                    'email' => $result['customerEmail'] ?? $result['email'] ?? null,
                    'name' => $result['customerName'] ?? $result['name'] ?? null,
                ],
            );
        } catch (GuzzleException $e) {
            $userMessage = $this->getNetworkErrorMessage($e);
            $this->log('error', 'Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            throw new VerificationException($userMessage, 0, $e);
        }
    }

    /**
     * Verify that a webhook is really from OPay (security check).
     *
     * OPay signs webhooks using HMAC SHA256 with your secret key.
     * The signature comes in the 'x-opay-signature' or 'signature' header.
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['x-opay-signature'][0]
            ?? $headers['X-OPay-Signature'][0]
            ?? $headers['signature'][0]
            ?? $headers['Signature'][0]
            ?? null;

        if (! $signature) {
            $this->log('warning', 'Webhook signature missing');

            return false;
        }

        $secretKey = $this->config['secret_key'] ?? $this->config['public_key'] ?? null;

        if (! $secretKey) {
            $this->log('warning', 'OPay secret key not configured for webhook validation');

            return false;
        }

        // OPay uses HMAC SHA256 for webhook validation
        $expectedSignature = hash_hmac('sha256', $body, $secretKey);

        $isValid = hash_equals($signature, $expectedSignature);

        $this->log($isValid ? 'info' : 'warning', 'Webhook validation', [
            'valid' => $isValid,
        ]);

        return $isValid;
    }

    /**
     * Check if OPay's API is working.
     */
    public function healthCheck(): bool
    {
        try {
            // Try a simple API call to check connectivity
            $response = $this->makeRequest('GET', '/api/v3/international/cashier/status');

            return $response->getStatusCode() < 500;
        } catch (ClientException $e) {
            // 4xx errors mean the API is working
            return $e->getResponse()->getStatusCode() < 500;
        } catch (GuzzleException $e) {
            // Network errors, timeouts, 5xx errors = unhealthy
            $this->log('error', 'Health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get the transaction reference from a raw webhook payload.
     */
    public function extractWebhookReference(array $payload): ?string
    {
        return $payload['reference'] ?? $payload['orderNo'] ?? $payload['orderNumber'] ?? null;
    }

    /**
     * Get the payment status from a raw webhook payload (in provider-native format).
     */
    public function extractWebhookStatus(array $payload): string
    {
        return $payload['status'] ?? $payload['orderStatus'] ?? 'unknown';
    }

    /**
     * Get the payment channel from a raw webhook payload.
     */
    public function extractWebhookChannel(array $payload): ?string
    {
        return $payload['paymentChannel'] ?? $payload['channel'] ?? null;
    }

    /**
     * Resolve the actual ID needed for verification.
     * OPay verifies by transaction reference.
     */
    public function resolveVerificationId(string $reference, string $providerId): string
    {
        // OPay verifies by the main transaction reference
        return $reference;
    }
}
