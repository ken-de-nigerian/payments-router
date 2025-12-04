<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\Drivers;

use GuzzleHttp\Exception\GuzzleException;
use KenDeNigerian\PaymentsRouter\DataObjects\ChargeRequest;
use KenDeNigerian\PaymentsRouter\DataObjects\ChargeResponse;
use KenDeNigerian\PaymentsRouter\DataObjects\VerificationResponse;
use KenDeNigerian\PaymentsRouter\Exceptions\ChargeException;
use KenDeNigerian\PaymentsRouter\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PaymentsRouter\Exceptions\VerificationException;

/**
 * Class PayPalDriver
 *
 * PayPal payment gateway driver
 */
class PayPalDriver extends AbstractDriver
{
    protected string $name = 'paypal';
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;

    /**
     * Validate configuration
     *
     * @throws InvalidConfigurationException
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new InvalidConfigurationException('PayPal client ID and secret are required');
        }
    }

    /**
     * Get default headers
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get or refresh access token
     *
     * @return string
     * @throws ChargeException
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        try {
            $credentials = base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']);

            $response = $this->makeRequest('POST', '/v1/oauth2/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = $this->parseResponse($response);

            if (!isset($data['access_token'])) {
                throw new ChargeException('Failed to authenticate with PayPal');
            }

            $this->accessToken = $data['access_token'];
            $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600) - 60;

            return $this->accessToken;
        } catch (GuzzleException $e) {
            throw new ChargeException('PayPal authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Initialize a charge
     *
     * @param ChargeRequest $request
     * @return ChargeResponse
     * @throws ChargeException
     */
    public function charge(ChargeRequest $request): ChargeResponse
    {
        try {
            $reference = $request->reference ?? $this->generateReference('PAYPAL');

            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $reference,
                        'description' => $request->description ?? 'Payment',
                        'amount' => [
                            'currency_code' => $request->currency,
                            'value' => number_format($request->amount, 2, '.', ''),
                        ],
                        'custom_id' => $reference,
                    ],
                ],
                'application_context' => [
                    'return_url' => $request->callbackUrl ?? $this->config['callback_url'],
                    'cancel_url' => $request->callbackUrl ?? $this->config['callback_url'],
                    'brand_name' => $this->config['brand_name'] ?? 'Your Store',
                    'user_action' => 'PAY_NOW',
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'user_action' => 'PAY_NOW',
                        ],
                    ],
                ],
            ];

            $response = $this->makeRequest('POST', '/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                ],
                'json' => $payload,
            ]);

            $data = $this->parseResponse($response);

            if (!isset($data['id'])) {
                throw new ChargeException('Failed to create PayPal order');
            }

            $approveLink = collect($data['links'] ?? [])->firstWhere('rel', 'approve');

            $this->log('info', 'Charge initialized successfully', [
                'reference' => $reference,
                'order_id' => $data['id'],
            ]);

            return new ChargeResponse(
                reference: $reference,
                authorizationUrl: $approveLink['href'] ?? '',
                accessCode: $data['id'],
                status: strtolower($data['status']),
                metadata: [
                    'order_id' => $data['id'],
                    'links' => $data['links'] ?? [],
                ],
                provider: $this->getName(),
            );
        } catch (GuzzleException $e) {
            $this->log('error', 'Charge failed', ['error' => $e->getMessage()]);
            throw new ChargeException('PayPal charge failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify a payment
     *
     * @param string $reference
     * @return VerificationResponse
     * @throws VerificationException
     */
    public function verify(string $reference): VerificationResponse
    {
        try {
            // Reference could be order_id or our custom reference
            $response = $this->makeRequest('GET', "/v2/checkout/orders/{$reference}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                ],
            ]);

            $data = $this->parseResponse($response);

            if (!isset($data['id'])) {
                throw new VerificationException('PayPal order not found');
            }

            $purchaseUnit = $data['purchase_units'][0] ?? [];
            $amount = $purchaseUnit['amount'] ?? [];
            $payments = $purchaseUnit['payments']['captures'][0] ?? null;

            $this->log('info', 'Payment verified', [
                'reference' => $reference,
                'status' => $data['status'],
            ]);

            return new VerificationResponse(
                reference: $purchaseUnit['custom_id'] ?? $reference,
                status: $this->normalizeStatus($data['status']),
                amount: (float) ($amount['value'] ?? 0),
                currency: $amount['currency_code'] ?? 'USD',
                paidAt: $payments['create_time'] ?? null,
                metadata: [
                    'order_id' => $data['id'],
                    'capture_id' => $payments['id'] ?? null,
                ],
                provider: $this->getName(),
                customer: [
                    'email' => $data['payer']['email_address'] ?? null,
                    'name' => $data['payer']['name']['given_name'] ?? null,
                ],
            );
        } catch (GuzzleException $e) {
            $this->log('error', 'Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new VerificationException('PayPal verification failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate webhook signature
     *
     * @param array $headers
     * @param string $body
     * @return bool
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        // PayPal webhook validation is complex and requires API calls
        // For production, implement full verification using PayPal's verification endpoint
        $transmissionId = $headers['paypal-transmission-id'][0] ?? null;
        $transmissionSig = $headers['paypal-transmission-sig'][0] ?? null;
        $certUrl = $headers['paypal-cert-url'][0] ?? null;
        $authAlgo = $headers['paypal-auth-algo'][0] ?? null;

        if (!$transmissionId || !$transmissionSig) {
            $this->log('warning', 'Webhook headers missing');
            return false;
        }

        // For simplicity, we'll just validate the presence of required headers
        // In production, verify using PayPal's webhook verification API
        $this->log('info', 'Webhook validation (simplified)', [
            'transmission_id' => $transmissionId,
        ]);

        return true;
    }

    /**
     * Health check
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        try {
            $this->getAccessToken();
            return true;
        } catch (\Exception $e) {
            $this->log('error', 'Health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Normalize status from PayPal to standard format
     *
     * @param string $status
     * @return string
     */
    private function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'COMPLETED', 'APPROVED' => 'success',
            'CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED' => 'pending',
            'VOIDED', 'CANCELLED' => 'cancelled',
            default => strtolower($status),
        };
    }
}
