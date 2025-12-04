<?php
namespace Nwaneri\PaymentsRouter\Drivers;

use Nwaneri\PaymentsRouter\Contracts\DriverInterface;
use Nwaneri\PaymentsRouter\Exceptions\PaymentException;
use Stripe\StripeClient;

class StripeDriver implements DriverInterface
{
    protected array $config;
    protected StripeClient $client;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new StripeClient($config['secret'] ?? null);
    }

    public function createCharge(array $payload): array
    {
        // Using PaymentIntents recommended for modern Stripe integrations.
        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => intval($payload['amount']),
                'currency' => strtolower($payload['currency'] ?? 'usd'),
                'metadata' => $payload['metadata'] ?? [],
                'receipt_email' => $payload['email'] ?? null,
            ]);
        } catch (\Throwable $e) {
            throw new PaymentException('Stripe create charge failed: ' . $e->getMessage());
        }

        // return client_secret & id for client JS
        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'status' => $intent->status,
        ];
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $intent = $this->client->paymentIntents->retrieve($reference);
        } catch (\Throwable $e) {
            throw new PaymentException('Stripe verify failed: ' . $e->getMessage());
        }

        return ['id' => $intent->id, 'status' => $intent->status, 'amount' => $intent->amount];
    }

    public function redirectResponse(array $createChargeResponse)
    {
        // For Stripe we usually return json to client with client_secret
        return response()->json($createChargeResponse);
    }

    public function validateWebhook(array $headers, string $body): bool
    {
        if (empty($this->config['secret'])) return false;
        $sigHeader = $headers['stripe-signature'][0] ?? ($headers['Stripe-Signature'][0] ?? null);
        if (!$sigHeader) return false;
        try {
            \Stripe\Webhook::constructEvent($body, $sigHeader, $this->config['endpoint_secret'] ?? null);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function healthCheck(): bool
    {
        try {
            // simple call to list products (lightweight)
            $this->client->products->all(['limit' => 1]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
