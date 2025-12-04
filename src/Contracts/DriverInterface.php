<?php
namespace Nwaneri\PaymentsRouter\Contracts;

interface DriverInterface
{
    public function __construct(array $config);
    public function createCharge(array $payload): array;
    public function verifyPayment(string $reference): array;
    public function redirectResponse(array $createChargeResponse);
    public function validateWebhook(array $headers, string $body): bool;
    public function healthCheck(): bool;
}
