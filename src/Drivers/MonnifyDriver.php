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

class MonnifyDriver extends AbstractDriver
{
    protected string $name = 'monnify';

    private ?string $accessToken = null;

    private ?int $tokenExpiry = null;

    protected function validateConfig(): void
    {
        if (empty($this->config['api_key']) || empty($this->config['secret_key'])) {
            throw new InvalidConfigurationException('Monnify API key and secret key are required');
        }
        if (empty($this->config['contract_code'])) {
            throw new InvalidConfigurationException('Monnify contract code is required');
        }
    }

    protected function getDefaultHeaders(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        try {
            $credentials = base64_encode($this->config['api_key'].':'.$this->config['secret_key']);
            $response = $this->makeRequest('POST', '/api/v1/auth/login', [
                'headers' => ['Authorization' => 'Basic '.$credentials],
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
                'redirectUrl' => $request->callbackUrl ?? $this->config['callback_url'] ?? null,
                'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER'],
                'metadata' => $request->metadata,
            ];

            $response = $this->makeRequest('POST', '/api/v1/merchant/transactions/init-transaction', [
                'headers' => ['Authorization' => 'Bearer '.$this->getAccessToken()],
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['requestSuccessful'] ?? false)) {
                throw new ChargeException($data['responseMessage'] ?? 'Failed to initialize Monnify transaction');
            }

            $result = $data['responseBody'];
            $this->log('info', 'Charge initialized successfully', ['reference' => $reference]);

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

    public function verify(string $reference): VerificationResponse
    {
        try {
            $response = $this->makeRequest('GET', "/api/v2/transactions/$reference", [
                'headers' => ['Authorization' => 'Bearer '.$this->getAccessToken()],
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['requestSuccessful'] ?? false)) {
                throw new VerificationException($data['responseMessage'] ?? 'Failed to verify Monnify transaction');
            }

            $result = $data['responseBody'];

            return new VerificationResponse(
                reference: $result['paymentReference'] ?? $reference,
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
            $this->log('error', 'Verification failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            throw new VerificationException('Monnify verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['monnify-signature'][0] ?? $headers['Monnify-Signature'][0] ?? null;
        if (! $signature) {
            return false;
        }
        $hash = hash_hmac('sha512', $body, $this->config['secret_key']);

        return hash_equals($signature, $hash);
    }

    public function healthCheck(): bool
    {
        try {
            $this->getAccessToken();

            return true;
        } catch (Exception) {
            return false;
        }
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'OVERPAID' => 'success',
            'PENDING', 'PARTIALLY_PAID' => 'pending',
            'FAILED', 'CANCELLED', 'EXPIRED' => 'failed',
            default => strtolower($status),
        };
    }
}
