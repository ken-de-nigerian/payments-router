<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Tests\Fixtures;

use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;
use KenDeNigerian\PayZephyr\Drivers\AbstractDriver;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;

/**
 * Example Custom Test Driver
 *
 * This demonstrates how to create a custom driver WITHOUT modifying
 * any core package files. This driver can be registered and used
 * with all existing PayZephyr infrastructure.
 */
final class CustomTestDriver extends AbstractDriver
{
    protected string $name = 'custom_test';

    /**
     * Validate that required configuration is present
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['api_key'])) {
            throw new InvalidConfigurationException('Custom driver API key is required');
        }
    }

    /**
     * Get default HTTP headers for API requests
     *
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get idempotency header format
     *
     * @return array<string, string>
     */
    protected function getIdempotencyHeader(string $key): array
    {
        return ['Idempotency-Key' => $key];
    }

    /**
     * Initialize a payment charge
     *
     * @throws ChargeException
     */
    public function charge(ChargeRequestDTO $request): ChargeResponseDTO
    {
        $this->setCurrentRequest($request);

        try {
            $reference = $request->reference ?? $this->generateReference('CUSTOM');

            $payload = [
                'amount' => $request->getAmountInMinorUnits(),
                'currency' => strtolower($request->currency),
                'email' => $request->email,
                'reference' => $reference,
                'callback_url' => $request->callbackUrl,
                'metadata' => $request->metadata,
            ];

            $response = $this->makeRequest('POST', '/payment/initialize', [
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['success'] ?? false)) {
                throw new ChargeException(
                    $data['message'] ?? 'Failed to initialize payment'
                );
            }

            $result = $data['data'];

            return new ChargeResponseDTO(
                reference: $reference,
                authorizationUrl: $result['authorization_url'] ?? $result['checkout_url'],
                accessCode: $result['access_code'] ?? $result['id'],
                status: 'pending',
                metadata: $request->metadata,
                provider: $this->getName(),
            );
        } catch (ChargeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ChargeException('Payment initialization failed: '.$e->getMessage(), 0, $e);
        } finally {
            $this->clearCurrentRequest();
        }
    }

    /**
     * Verify a payment
     *
     * @throws VerificationException
     */
    public function verify(string $reference): VerificationResponseDTO
    {
        try {
            $response = $this->makeRequest('GET', "/payment/verify/{$reference}");
            $data = $this->parseResponse($response);

            if (! ($data['success'] ?? false)) {
                throw new VerificationException(
                    $data['message'] ?? 'Failed to verify payment'
                );
            }

            $result = $data['data'];

            return new VerificationResponseDTO(
                reference: $result['reference'] ?? $reference,
                status: $this->normalizeStatus($result['status'] ?? 'unknown'),
                amount: ($result['amount'] ?? 0) / 100,
                currency: strtoupper($result['currency'] ?? 'NGN'),
                paidAt: $result['paid_at'] ?? null,
                metadata: $result['metadata'] ?? [],
                provider: $this->getName(),
                channel: $result['channel'] ?? null,
            );
        } catch (VerificationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new VerificationException('Payment verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['x-custom-signature'][0] ?? null;

        if (! $signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $body, $this->config['webhook_secret'] ?? '');

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Health check
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/health');
            $data = $this->parseResponse($response);

            return ($data['status'] ?? 'ok') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Extract webhook reference
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookReference(array $payload): ?string
    {
        return $payload['data']['reference'] ?? $payload['reference'] ?? null;
    }

    /**
     * Extract webhook status
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookStatus(array $payload): string
    {
        return $payload['data']['status'] ?? $payload['status'] ?? 'unknown';
    }

    /**
     * Extract webhook channel
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookChannel(array $payload): ?string
    {
        return $payload['data']['channel'] ?? $payload['channel'] ?? null;
    }

    /**
     * Resolve verification ID
     */
    public function resolveVerificationId(string $reference, string $providerId): string
    {
        return $reference;
    }

    /**
     * Normalize status to unified format
     */
    protected function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'succeeded', 'completed', 'paid' => 'success',
            'pending', 'processing' => 'pending',
            'failed', 'declined', 'cancelled' => 'failed',
            default => 'unknown',
        };
    }
}
