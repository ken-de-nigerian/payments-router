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
 * RemitaDriver - Handles Payments via Remita
 *
 * This driver processes payments through Remita's API.
 * Remita uses RRR (Remita Retrieval Reference) for payment processing.
 * When you initialize a payment, it generates an RRR that customers can use
 * to pay via various channels (bank, card, USSD, etc.).
 */
final class RemitaDriver extends AbstractDriver
{
    protected string $name = 'remita';

    /**
     * Make sure all required Remita credentials are configured.
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['public_key'])) {
            throw new InvalidConfigurationException('Remita public key is required');
        }
        if (empty($this->config['secret_key'])) {
            throw new InvalidConfigurationException('Remita secret key is required');
        }
    }

    /**
     * Get the HTTP headers needed for Remita API requests.
     * Remita uses public key as API key and secret key for signing.
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'API-Key' => $this->config['public_key'],
        ];
    }

    /**
     * Remita uses 'Idempotency-Key' header.
     */
    protected function getIdempotencyHeader(string $key): array
    {
        return ['Idempotency-Key' => $key];
    }

    /**
     * Create a new payment on Remita.
     *
     * Remita generates an RRR (Remita Retrieval Reference) that customers use to pay.
     * The amount should be in the smallest currency unit (kobo for NGN).
     *
     * @throws ChargeException If the payment creation fails.
     * @throws RandomException If reference generation fails.
     */
    public function charge(ChargeRequestDTO $request): ChargeResponseDTO
    {
        $this->setCurrentRequest($request);

        try {
            $reference = $request->reference ?? $this->generateReference('REMITA');
            $amount = $request->getAmountInMinorUnits();

            $payload = [
                'amount' => $amount,
                'orderId' => $reference,
                'payerName' => $request->email,
                'payerEmail' => $request->email,
                'payerPhone' => $request->metadata['phone'] ?? null,
                'description' => $request->metadata['description'] ?? 'Payment for '.$reference,
                'callbackUrl' => $request->callbackUrl,
            ];

            $hashString = $reference.$amount.$this->config['secret_key'];
            $hash = hash('sha512', $hashString);
            $payload['hash'] = $hash;

            // Remove null values
            $payload = array_filter($payload, fn ($value) => $value !== null);

            $response = $this->makeRequest('POST', '/api/v1/payments/initialize', [
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            // Remita returns statusCode: '00' = success, '01' = pending, others = failed
            $statusCode = $data['statusCode'] ?? '';
            if (! in_array($statusCode, ['00', '01'], true)) {
                throw new ChargeException(
                    $data['statusMessage'] ?? $data['message'] ?? 'Failed to initialize Remita payment'
                );
            }

            $rrr = $data['RRR'] ?? $data['rrr'] ?? null;

            if (! $rrr) {
                throw new ChargeException('Failed to generate Remita RRR');
            }

            $this->log('info', 'Charge initialized successfully', [
                'reference' => $reference,
                'rrr' => $rrr,
            ]);

            // Remita payment URL
            $paymentUrl = ($this->config['base_url'] ?? 'https://remitademo.net').'/remita/ecomm/initreg/'.$rrr;

            return new ChargeResponseDTO(
                reference: $reference,
                authorizationUrl: $paymentUrl,
                accessCode: $rrr,
                status: 'pending',
                metadata: array_merge($request->metadata ?? [], ['rrr' => $rrr]),
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
     * Verify a Remita payment by RRR.
     *
     * Looks up the transaction by RRR and returns the payment details.
     *
     * @param  string  $reference  The RRR (Remita Retrieval Reference) or order ID
     *
     * @throws VerificationException If the payment can't be found or verified.
     */
    public function verify(string $reference): VerificationResponseDTO
    {
        try {
            $hashString = $reference.$this->config['secret_key'];
            $hash = hash('sha512', $hashString);

            $response = $this->makeRequest('GET', "/api/v1/payments/status/$reference", [
                'headers' => [
                    'Hash' => $hash,
                ],
            ]);

            $data = $this->parseResponse($response);

            // Check if response indicates success
            if (($data['statusCode'] ?? '') !== '00' && ($data['statusCode'] ?? '') !== '01') {
                throw new VerificationException(
                    $data['statusMessage'] ?? $data['message'] ?? 'Failed to verify Remita transaction'
                );
            }

            $result = $data['responseData'] ?? $data;

            $this->log('info', 'Payment verified', [
                'reference' => $reference,
                'status' => $result['status'] ?? $result['statusCode'] ?? 'unknown',
            ]);

            $remitaStatus = $result['status'] ?? $result['statusCode'] ?? 'unknown';
            $status = match ($remitaStatus) {
                '00' => 'success',
                '01' => 'pending',
                default => $this->normalizeStatus($remitaStatus),
            };

            return new VerificationResponseDTO(
                reference: $result['orderId'] ?? $result['rrr'] ?? $reference,
                status: $status,
                amount: ($result['amount'] ?? 0) / 100,
                currency: $result['currency'] ?? 'NGN',
                paidAt: $result['paymentDate'] ?? $result['transactionDate'] ?? null,
                metadata: $result['metadata'] ?? [],
                provider: $this->getName(),
                channel: $result['paymentChannel'] ?? null,
                customer: [
                    'email' => $result['payerEmail'] ?? null,
                    'name' => $result['payerName'] ?? null,
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
     * Verify that a webhook is really from Remita (security check).
     *
     * Remita signs webhooks using HMAC SHA512 with your API key.
     * The signature comes in the 'remita-hash' or 'hash' header.
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['remita-hash'][0]
            ?? $headers['Remita-Hash'][0]
            ?? $headers['hash'][0]
            ?? $headers['Hash'][0]
            ?? null;

        if (! $signature) {
            $this->log('warning', 'Webhook signature missing');

            return false;
        }

        $secretKey = $this->config['secret_key'] ?? null;

        if (! $secretKey) {
            $this->log('warning', 'Remita secret key not configured for webhook validation');

            return false;
        }

        // Remita uses SHA512 hash with secret key
        $expectedHash = hash('sha512', $body.$secretKey);

        $isValid = hash_equals($signature, $expectedHash);

        $this->log($isValid ? 'info' : 'warning', 'Webhook validation', [
            'valid' => $isValid,
        ]);

        return $isValid;
    }

    /**
     * Check if Remita's API is working.
     */
    public function healthCheck(): bool
    {
        try {
            // Try a simple API call to check connectivity
            $response = $this->makeRequest('GET', '/api/v1/health');

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
        return $payload['orderId'] ?? $payload['rrr'] ?? $payload['RRR'] ?? null;
    }

    /**
     * Get the payment status from a raw webhook payload (in provider-native format).
     */
    public function extractWebhookStatus(array $payload): string
    {
        return $payload['status'] ?? $payload['statusCode'] ?? 'unknown';
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
     * Remita verifies by RRR or order ID.
     */
    public function resolveVerificationId(string $reference, string $providerId): string
    {
        // Remita uses RRR for verification, which is stored in accessCode
        return $providerId ?: $reference;
    }
}
