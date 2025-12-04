<?php
namespace Nwaneri\PaymentsRouter\Drivers;

use Nwaneri\PaymentsRouter\Contracts\DriverInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Nwaneri\PaymentsRouter\Exceptions\PaymentException;

class PaystackDriver implements DriverInterface
{
    protected array $config;
    protected Client $http;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->http = new Client([
            'base_uri' => $config['base_url'] ?? 'https://api.paystack.co',
            'headers' => [
                'Authorization' => 'Bearer ' . ($config['secret'] ?? ''),
                'Accept' => 'application/json',
            ],
            'timeout' => 10,
        ]);
    }

    public function createCharge(array $payload): array
    {
        $body = [
            'email' => $payload['email'] ?? null,
            'amount' => intval($payload['amount']),
            'metadata' => $payload['metadata'] ?? [],
            'callback_url' => $payload['callback_url'] ?? null,
            'currency' => $payload['currency'] ?? null,
        ];

        try {
            $res = $this->http->post('/transaction/initialize', ['json' => $body]);
            $data = json_decode((string)$res->getBody(), true);
        } catch (RequestException $e) {
            throw new PaymentException('Paystack request failed: ' . $e->getMessage());
        }

        if (!($data['status'] ?? false)) {
            throw new PaymentException('Paystack init failed: ' . ($data['message'] ?? 'unknown'));
        }

        return $data['data'];
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $res = $this->http->get("/transaction/verify/{$reference}");
            $data = json_decode((string)$res->getBody(), true);
        } catch (RequestException $e) {
            throw new PaymentException('Paystack verify failed: ' . $e->getMessage());
        }

        if (!($data['status'] ?? false)) {
            throw new PaymentException('Paystack verify failed: ' . ($data['message'] ?? 'unknown'));
        }

        return $data['data'];
    }

    public function redirectResponse(array $createChargeResponse)
    {
        $url = $createChargeResponse['authorization_url'] ?? ($createChargeResponse['redirect_url'] ?? null);
        return redirect()->away($url ?: '/');
    }

    public function validateWebhook(array $headers, string $body): bool
    {
        if (empty($this->config['secret'])) return false;
        $signature = $headers['x-paystack-signature'][0] ?? ($headers['X-Paystack-Signature'][0] ?? null);
        if (!$signature) return false;
        return hash_equals($signature, hash_hmac('sha256', $body, $this->config['secret']));
    }

    public function healthCheck(): bool
    {
        try {
            $res = $this->http->get('/transaction/verify/000000');
            return in_array($res->getStatusCode(), [200, 400, 404]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
