<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequest;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponse;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponse;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;
use Random\RandomException;

/**
 * Class MonnifyDriver
 *
 * Monnify payment gateway driver
 */
class MonnifyDriver extends AbstractDriver
{
    protected string $name = 'monnify';

    private ?string $accessToken = null;

    private ?int $tokenExpiry = null;

    /**
     * Validate configuration
     *
     * @throws InvalidConfigurationException
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['api_key']) || empty($this->config['secret_key'])) {
            throw new InvalidConfigurationException('Monnify API key and secret key are required');
        }

        if (empty($this->config['contract_code'])) {
            throw new InvalidConfigurationException('Monnify contract code is required');
        }
    }

    /**
     * Get default headers
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get or refresh access token
     *
     * @throws ChargeException
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        try {
            $credentials = base64_encode($this->config['api_key'].':'.$this->config['secret_key']);

            $response = $this->makeRequest('POST', '/api/v1/auth/login', [
                'headers' => [
                    'Authorization' => 'Basic '.$credentials,
                ],
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['requestSuccessful'] ?? false)) {
                throw new ChargeException('Failed to authenticate with Monnify');
            }

            $this->accessToken = $data['responseBody']['accessToken'];
            $this->tokenExpiry = time() + ($data['responseBody']['expiresIn'] ?? 3600) - 60;

            return $this->accessToken;
        } catch (GuzzleException $e) {
            throw new ChargeException('Monnify authentication failed: '.$e->getMessage(), 0, $e);
        }
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
            $reference = $request->reference ?? $this->generateReference('MON');

            $payload = [
                'amount' => $request->amount,
                'customerName' => $request->customer['name'] ?? 'Customer',
                'customerEmail' => $request->email,
                'paymentReference' => $reference,
                'paymentDescription' => $request->description ?? 'Payment',
                'currencyCode' => $request->currency,
                'contractCode' => $this->config['contract_code'],
                'redirectUrl' => $request->callbackUrl ?? $this->config['callback_url'],
                'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER'],
                'metadata' => $request->metadata,
            ];

            $response = $this->makeRequest('POST', '/api/v1/merchant/transactions/init-transaction', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getAccessToken(),
                ],
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['requestSuccessful'] ?? false)) {
                throw new ChargeException(
                    $data['responseMessage'] ?? 'Failed to initialize Monnify transaction'
                );
            }

            $result = $data['responseBody'];

            $this->log('info', 'Charge initialized successfully', [
                'reference' => $reference,
            ]);

            return new ChargeResponse(
                reference: $reference,
                authorizationUrl: $result['checkoutUrl'],
                accessCode: $result['transactionReference'],
                status: 'pending',
                metadata: $request->metadata,
                provider: $this->getName(),
            );
        } catch (GuzzleException $e) {
            $this->log('error', 'Charge failed', ['error' => $e->getMessage()]);
            throw new ChargeException('Monnify charge failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify a payment
     *
     * @throws VerificationException
     * @throws ChargeException
     */
    public function verify(string $reference): VerificationResponse
    {
        try {
            $response = $this->makeRequest(
                'GET',
                "/api/v2/transactions/$reference",
                [
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->getAccessToken(),
                    ],
                ]
            );

            $data = $this->parseResponse($response);

            if (! ($data['requestSuccessful'] ?? false)) {
                throw new VerificationException(
                    $data['responseMessage'] ?? 'Failed to verify Monnify transaction'
                );
            }

            $result = $data['responseBody'];

            $this->log('info', 'Payment verified', [
                'reference' => $reference,
                'status' => $result['paymentStatus'],
            ]);

            return new VerificationResponse(
                reference: $result['paymentReference'],
                status: $this->normalizeStatus($result['paymentStatus']),
                amount: (float) $result['amountPaid'],
                currency: $result['currencyCode'],
                paidAt: $result['paidOn'] ?? null,
                metadata: $result['metaData'] ?? [],
                provider: $this->getName(),
                channel: $result['paymentMethod'] ?? null,
                customer: [
                    'email' => $result['customerEmail'] ?? null,
                    'name' => $result['customerName'] ?? null,
                ],
            );
        } catch (GuzzleException $e) {
            $this->log('error', 'Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new VerificationException('Monnify verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['monnify-signature'][0]
            ?? $headers['Monnify-Signature'][0]
            ?? null;

        if (! $signature) {
            $this->log('warning', 'Webhook signature missing');

            return false;
        }

        $hash = hash_hmac('sha512', $body, $this->config['secret_key']);

        $isValid = hash_equals($signature, $hash);

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
            $this->getAccessToken();

            return true;
        } catch (Exception $e) {
            $this->log('error', 'Health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Normalize status from Monnify to standard format
     */
    private function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID' => 'success',
            'PENDING' => 'pending',
            'OVERPAID' => 'success',
            'PARTIALLY_PAID' => 'pending',
            'FAILED', 'CANCELLED', 'EXPIRED' => 'failed',
            default => strtolower($status),
        };
    }
}
