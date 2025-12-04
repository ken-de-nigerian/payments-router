<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\Contracts;

use KenDeNigerian\PaymentsRouter\DataObjects\ChargeRequest;
use KenDeNigerian\PaymentsRouter\DataObjects\ChargeResponse;
use KenDeNigerian\PaymentsRouter\DataObjects\VerificationResponse;

/**
 * Interface DriverInterface
 *
 * All payment provider drivers must implement this interface
 */
interface DriverInterface
{
    /**
     * Initialize a charge/payment
     *
     * @param ChargeRequest $request
     * @return ChargeResponse
     * @throws \KenDeNigerian\PaymentsRouter\Exceptions\PaymentException
     */
    public function charge(ChargeRequest $request): ChargeResponse;

    /**
     * Verify a payment transaction
     *
     * @param string $reference Transaction reference
     * @return VerificationResponse
     * @throws \KenDeNigerian\PaymentsRouter\Exceptions\PaymentException
     */
    public function verify(string $reference): VerificationResponse;

    /**
     * Validate webhook signature
     *
     * @param array $headers Request headers
     * @param string $body Raw request body
     * @return bool
     */
    public function validateWebhook(array $headers, string $body): bool;

    /**
     * Check if the provider is available and healthy
     *
     * @return bool
     */
    public function healthCheck(): bool;

    /**
     * Get the provider name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function getSupportedCurrencies(): array;
}
