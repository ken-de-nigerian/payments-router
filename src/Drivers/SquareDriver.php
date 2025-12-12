<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use GuzzleHttp\Exception\ClientException;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Exceptions\PaymentException;
use KenDeNigerian\PayZephyr\Exceptions\VerificationException;
use Square\Checkout\PaymentLinks\Requests\CreatePaymentLinkRequest;
use Square\Checkout\PaymentLinks\Requests\GetPaymentLinksRequest;
use Square\Environments;
use Square\Exceptions\SquareApiException;
use Square\Exceptions\SquareException;
use Square\Orders\Requests\GetOrdersRequest;
use Square\Orders\Requests\SearchOrdersRequest;
use Square\Payments\Requests\GetPaymentsRequest;
use Square\SquareClient;
use Square\Types\CheckoutOptions;
use Square\Types\Money;
use Square\Types\Order;
use Square\Types\OrderLineItem;
use Square\Types\OrderState;
use Square\Types\Payment;
use Square\Types\PrePopulatedData;
use Square\Types\SearchOrdersFilter;
use Square\Types\SearchOrdersQuery;
use Square\Types\SearchOrdersStateFilter;
use Throwable;

/**
 * Driver implementation for the Square payment gateway.
 */
final class SquareDriver extends AbstractDriver
{
    protected string $name = 'square';

    /**
     * Square SDK client instance.
     */
    private ?SquareClient $squareClient = null;

    /**
     * Make sure the Square secret key is configured.
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['access_token'])) {
            throw new InvalidConfigurationException('Square access token is required');
        }
        if (empty($this->config['location_id'])) {
            throw new InvalidConfigurationException('Square location ID is required');
        }
    }

    /**
     * Get or create the Square SDK client instance.
     */
    private function getSquareClient(): SquareClient
    {
        if ($this->squareClient === null) {
            $isSandbox = str_contains($this->config['base_url'] ?? '', 'squareupsandbox.com');
            $environment = $isSandbox ? Environments::Sandbox : Environments::Production;

            $options = [
                'baseUrl' => $environment->value,
            ];

            if (isset($this->client)) {
                $options['client'] = $this->client;
            }

            $this->squareClient = new SquareClient(
                token: $this->config['access_token'],
                version: '2024-10-18',
                options: $options
            );
        }

        return $this->squareClient;
    }

    /**
     * Get the HTTP headers needed for Square API requests.
     * Note: This is kept for backward compatibility but may not be used when using SDK.
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['access_token'],
            'Content-Type' => 'application/json',
            'Square-Version' => '2024-10-18',
        ];
    }

    /**
     * Square uses the standard 'Idempotency-Key' header.
     */
    protected function getIdempotencyHeader(string $key): array
    {
        return ['Idempotency-Key' => $key];
    }

    /**
     * Create a new payment link on Square using the Square PHP SDK.
     *
     * @throws ChargeException
     */
    public function charge(ChargeRequestDTO $request): ChargeResponseDTO
    {
        $this->setCurrentRequest($request);

        try {
            $reference = $request->reference ?? $this->generateReference('SQUARE');

            $amountMoney = new Money([
                'amount' => $request->getAmountInMinorUnits(),
                'currency' => $request->currency,
            ]);

            $lineItem = new OrderLineItem([
                'quantity' => '1',
                'name' => $request->description ?? 'Payment',
                'basePriceMoney' => $amountMoney,
            ]);

            $order = new Order([
                'locationId' => $this->config['location_id'],
                'referenceId' => $reference,
                'lineItems' => [$lineItem],
            ]);

            $checkoutOptions = new CheckoutOptions([
                'redirectUrl' => $this->appendQueryParam($request->callbackUrl, 'reference', $reference),
            ]);

            $prePopulatedData = new PrePopulatedData([
                'buyerEmail' => $request->email,
            ]);

            $paymentLinkRequest = new CreatePaymentLinkRequest([
                'idempotencyKey' => $request->idempotencyKey,
                'order' => $order,
                'checkoutOptions' => $checkoutOptions,
                'prePopulatedData' => $prePopulatedData,
            ]);

            $client = $this->getSquareClient();
            try {
                $response = $client->checkout->paymentLinks->create($paymentLinkRequest);
            } catch (SquareApiException $e) {
                $body = json_decode($e->getBody(), true);
                $errorMessage = $body['errors'][0]['detail'] ?? $e->getMessage();
                throw new ChargeException($errorMessage, 0, $e);
            } catch (SquareException $e) {
                throw new ChargeException('Payment initialization failed: '.$e->getMessage(), 0, $e);
            }

            $errors = $response->getErrors();
            if (! empty($errors)) {
                $errorMessage = $errors[0]->getDetail() ?? 'Failed to create Square payment link';
                throw new ChargeException($errorMessage);
            }

            $paymentLink = $response->getPaymentLink();
            if (! $paymentLink) {
                throw new ChargeException('Failed to create Square payment link');
            }
            $paymentLinkUrl = $paymentLink->getUrl();
            $paymentLinkId = $paymentLink->getId();
            $orderId = $paymentLink->getOrderId();
            $isSandbox = str_contains($this->config['base_url'] ?? '', 'squareupsandbox.com');

            $this->log('info', 'Charge initialized successfully', [
                'reference' => $reference,
            ]);

            return new ChargeResponseDTO(
                reference: $reference,
                authorizationUrl: $paymentLinkUrl,
                accessCode: $paymentLinkId,
                status: 'pending',
                metadata: [
                    'payment_link_id' => $paymentLinkId,
                    'order_id' => $orderId,
                    'is_sandbox' => $isSandbox,
                ],
                provider: $this->getName(),
            );
        } catch (ChargeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Charge failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            throw new ChargeException('Payment initialization failed: '.$e->getMessage(), 0, $e);
        } finally {
            $this->clearCurrentRequest();
        }
    }

    /**
     * Verify a payment by retrieving the payment details.
     *
     * @throws VerificationException
     */
    public function verify(string $reference): VerificationResponseDTO
    {
        try {
            $result = $this->verifyByPaymentId($reference);
            if ($result !== null) {
                return $result;
            }

            $result = $this->verifyByPaymentLinkId($reference);
            if ($result !== null) {
                return $result;
            }

            return $this->verifyByReferenceId($reference);
        } catch (VerificationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            throw new VerificationException('Payment verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Attempt to verify payment using a direct payment ID.
     *
     * @return VerificationResponseDTO|null Returns null if the reference is not a payment ID or payment not found
     *
     * @throws VerificationException
     */
    private function verifyByPaymentId(string $reference): ?VerificationResponseDTO
    {
        if (! str_starts_with($reference, 'payment_') && strlen($reference) !== 32) {
            return null;
        }

        try {
            $client = $this->getSquareClient();
            $request = new GetPaymentsRequest(['paymentId' => $reference]);
            $response = $client->payments->get($request);

            $errors = $response->getErrors();
            if (! empty($errors)) {
                $error = $errors[0];
                if ($error->getCategory() === 'NOT_FOUND_ERROR') {
                    return null;
                }
                throw new VerificationException($error->getDetail() ?? 'Failed to retrieve payment');
            }

            $payment = $response->getPayment();
            if (! $payment) {
                return null;
            }

            $paymentArray = $this->paymentToArray($payment);

            return $this->mapFromPayment($paymentArray, $reference);
        } catch (VerificationException $e) {
            throw $e;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'NOT_FOUND') || str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw new VerificationException('Payment verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Attempt to verify payment using a payment link ID.
     * Payment link IDs are typically alphanumeric strings (e.g., JE6RV44VZEML32Z2).
     *
     * @return VerificationResponseDTO|null Returns null if the reference is not a payment link ID or payment not found
     *
     * @throws VerificationException
     */
    private function verifyByPaymentLinkId(string $reference): ?VerificationResponseDTO
    {
        try {
            $client = $this->getSquareClient();
            $request = new GetPaymentLinksRequest(['id' => $reference]);
            $response = $client->checkout->paymentLinks->get($request);

            $errors = $response->getErrors();
            if (! empty($errors)) {
                $error = $errors[0];
                if ($error->getCategory() === 'NOT_FOUND_ERROR') {
                    return null;
                }
                throw new VerificationException($error->getDetail() ?? 'Failed to retrieve payment link');
            }

            $paymentLink = $response->getPaymentLink();
            $orderId = $paymentLink->getOrderId();

            if (! $orderId) {
                return null;
            }

            $order = $this->getOrderById($orderId);
            $payment = $this->getPaymentFromOrder($order, $orderId);
            $paymentDetails = $this->getPaymentDetails($payment);
            $actualReference = $order->getReferenceId() ?? $reference;

            $paymentArray = $this->paymentToArray($paymentDetails);

            return $this->mapFromPayment($paymentArray, $actualReference);
        } catch (VerificationException $e) {
            throw $e;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'NOT_FOUND') || str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw new VerificationException('Payment verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify payment by searching orders using reference_id.
     *
     * @throws VerificationException
     */
    private function verifyByReferenceId(string $reference): VerificationResponseDTO
    {
        try {
            $orders = $this->searchOrders();
        } catch (VerificationException $e) {
            if (str_contains($e->getMessage(), 'deserialize') || str_contains($e->getMessage(), 'serialize')) {
                return $this->verifyByReferenceIdHttp($reference);
            }
            throw $e;
        }

        $foundOrder = null;
        foreach ($orders as $order) {
            if ($order->getReferenceId() === $reference) {
                $foundOrder = $order;
                break;
            }
        }

        if (! $foundOrder) {
            throw new VerificationException("Payment not found for reference [$reference]");
        }

        $orderId = $foundOrder->getId();
        $order = $this->getOrderById($orderId);
        $payment = $this->getPaymentFromOrder($order, $orderId);
        $paymentDetails = $this->getPaymentDetails($payment);

        $paymentArray = $this->paymentToArray($paymentDetails);

        return $this->mapFromPayment($paymentArray, $reference);
    }

    /**
     * Fallback method to verify by reference ID using HTTP requests.
     * Used when SDK deserialization fails (e.g., in tests with incomplete mocks).
     *
     * @throws VerificationException
     */
    private function verifyByReferenceIdHttp(string $reference): VerificationResponseDTO
    {
        try {
            $response = $this->makeRequest('POST', '/v2/orders/search', [
                'json' => [
                    'location_ids' => [$this->config['location_id']],
                    'query' => [
                        'filter' => [
                            'state_filter' => [
                                'states' => ['OPEN', 'COMPLETED', 'CANCELED'],
                            ],
                        ],
                    ],
                ],
            ]);

            $data = $this->parseResponse($response);
            $orders = $data['orders'] ?? [];

            $foundOrder = null;
            foreach ($orders as $order) {
                if (($order['reference_id'] ?? null) === $reference) {
                    $foundOrder = $order;
                    break;
                }
            }

            if (! $foundOrder) {
                throw new VerificationException("Payment not found for reference [$reference]");
            }

            $orderId = $foundOrder['id'];
            $orderResponse = $this->makeRequest('GET', "/v2/orders/$orderId");
            $orderData = $this->parseResponse($orderResponse);
            $order = $orderData['order'] ?? null;

            if (! $order) {
                throw new VerificationException("Order not found for ID [$orderId]");
            }

            $tenders = $order['tenders'] ?? [];
            if (empty($tenders)) {
                throw new VerificationException("No payment found for order [$orderId]");
            }

            $paymentId = $tenders[0]['payment_id'] ?? null;
            if (! $paymentId) {
                throw new VerificationException("Payment ID not found for order [$orderId]");
            }

            $paymentResponse = $this->makeRequest('GET', "/v2/payments/$paymentId");
            $paymentData = $this->parseResponse($paymentResponse);

            return $this->mapFromPayment($paymentData['payment'], $reference);
        } catch (ClientException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                throw new VerificationException('Payment not found');
            }
            throw new VerificationException('Payment verification failed: '.$e->getMessage(), 0, $e);
        } catch (ChargeException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof ClientException && $previous->getResponse()?->getStatusCode() === 404) {
                throw new VerificationException('Payment not found');
            }
            throw new VerificationException('Payment verification failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Search orders using Square's order search API.
     *
     * @return array List of Order objects
     *
     * @throws VerificationException
     */
    private function searchOrders(): array
    {
        try {
            $client = $this->getSquareClient();

            $stateFilter = new SearchOrdersStateFilter([
                'states' => [
                    OrderState::Open,
                    OrderState::Completed,
                    OrderState::Canceled,
                ],
            ]);

            $filter = new SearchOrdersFilter([
                'stateFilter' => $stateFilter,
            ]);

            $query = new SearchOrdersQuery([
                'filter' => $filter,
            ]);

            $searchOrdersRequest = new SearchOrdersRequest([
                'locationIds' => [$this->config['location_id']],
                'query' => $query,
            ]);

            $response = $client->orders->search($searchOrdersRequest);

            $errors = $response->getErrors();
            if (! empty($errors)) {
                throw new VerificationException($errors[0]->getDetail() ?? 'Failed to search orders');
            }

            return $response->getOrders() ?? [];
        } catch (SquareException $e) {
            throw new VerificationException('Order search failed: '.$e->getMessage(), 0, $e);
        } catch (VerificationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new VerificationException('Order search failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Retrieve an order by ID.
     *
     * @return Order Order object
     *
     * @throws VerificationException
     */
    private function getOrderById(string $orderId): Order
    {
        try {
            $client = $this->getSquareClient();
            $request = new GetOrdersRequest(['orderId' => $orderId]);
            $response = $client->orders->get($request);

            $errors = $response->getErrors();
            if (! empty($errors)) {
                throw new VerificationException($errors[0]->getDetail() ?? "Order not found for ID [$orderId]");
            }

            $order = $response->getOrder();
            if (! $order) {
                throw new VerificationException("Order not found for ID [$orderId]");
            }

            return $order;
        } catch (VerificationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new VerificationException("Failed to retrieve order: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Extract payment ID from an order's tenders.
     *
     * @return string Payment ID
     *
     * @throws VerificationException
     */
    private function getPaymentFromOrder(Order $order, string $orderId): string
    {
        $tenders = $order->getTenders();
        if (empty($tenders)) {
            throw new VerificationException("No payment found for order [$orderId]");
        }

        $paymentId = $tenders[0]->getPaymentId();
        if (! $paymentId) {
            throw new VerificationException("Payment ID not found for order [$orderId]");
        }

        return $paymentId;
    }

    /**
     * Retrieve payment details by payment ID.
     *
     * @return Payment Payment object
     *
     * @throws VerificationException
     */
    private function getPaymentDetails(string $paymentId): Payment
    {
        try {
            $client = $this->getSquareClient();
            $request = new GetPaymentsRequest(['paymentId' => $paymentId]);
            $response = $client->payments->get($request);

            $errors = $response->getErrors();
            if (! empty($errors)) {
                throw new VerificationException($errors[0]->getDetail() ?? "Payment not found for ID [$paymentId]");
            }

            $payment = $response->getPayment();
            if (! $payment) {
                throw new VerificationException("Payment not found for ID [$paymentId]");
            }

            return $payment;
        } catch (VerificationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new VerificationException("Failed to retrieve payment: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Convert Square Payment object to array format for mapping.
     */
    private function paymentToArray($payment): array
    {
        if (is_array($payment)) {
            return $payment;
        }

        $amountMoney = $payment->getAmountMoney();
        $cardDetails = $payment->getCardDetails();

        return [
            'id' => $payment->getId(),
            'status' => $payment->getStatus(),
            'amount_money' => [
                'amount' => $amountMoney?->getAmount() ?? 0,
                'currency' => $amountMoney?->getCurrency() ?? 'USD',
            ],
            'reference_id' => $payment->getReferenceId(),
            'order_id' => $payment->getOrderId(),
            'source_type' => $payment->getSourceType(),
            'updated_at' => $payment->getUpdatedAt(),
            'created_at' => $payment->getCreatedAt(),
            'buyer_email_address' => $payment->getBuyerEmailAddress(),
            'card_details' => $cardDetails ? [
                'card' => [
                    'card_brand' => $cardDetails->getCard()?->getCardBrand(),
                ],
            ] : null,
        ];
    }

    /**
     * Map Square payment data to VerificationResponseDTO.
     */
    private function mapFromPayment(array $payment, string $reference): VerificationResponseDTO
    {
        $status = match (strtoupper($payment['status'] ?? '')) {
            'COMPLETED', 'APPROVED' => 'success',
            'FAILED', 'CANCELED' => 'failed',
            default => 'pending',
        };

        $cardDetails = $payment['card_details'] ?? null;
        $cardBrand = null;
        if ($cardDetails && isset($cardDetails['card']['card_brand'])) {
            $cardBrand = $cardDetails['card']['card_brand'];
        }

        return new VerificationResponseDTO(
            reference: $payment['reference_id'] ?? $reference,
            status: $status,
            amount: ($payment['amount_money']['amount'] ?? 0) / 100,
            currency: strtoupper($payment['amount_money']['currency'] ?? 'USD'),
            paidAt: $status === 'success' ? ($payment['updated_at'] ?? $payment['created_at'] ?? null) : null,
            metadata: [
                'payment_id' => $payment['id'] ?? null,
                'order_id' => $payment['order_id'] ?? null,
            ],
            provider: $this->getName(),
            channel: $payment['source_type'] ?? 'card',
            cardType: $cardBrand,
            customer: [
                'email' => $payment['buyer_email_address'] ?? null,
            ],
        );
    }

    /**
     * Validate the webhook signature.
     *
     * Square uses HMAC SHA256 with base64 encoding for webhook signatures.
     * The signature is sent in the 'x-square-signature' header.
     */
    public function validateWebhook(array $headers, string $body): bool
    {
        $signature = $headers['x-square-signature'][0]
            ?? $headers['X-Square-Signature'][0]
            ?? null;

        if (! $signature) {
            $this->log('warning', 'Webhook signature missing');

            return false;
        }

        $webhookSignatureKey = $this->config['webhook_signature_key'] ?? null;

        if (! $webhookSignatureKey) {
            $this->log('warning', 'Webhook signature key not configured', [
                'hint' => 'Set SQUARE_WEBHOOK_SIGNATURE_KEY in your .env file. Get it from Square Dashboard → Developers → Webhooks → Select endpoint → Signature Key',
            ]);

            return false;
        }

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $body, $webhookSignatureKey, true)
        );

        $isValid = hash_equals($signature, $expectedSignature);

        if ($isValid) {
            $this->log('info', 'Webhook validated successfully');
        } else {
            $this->log('warning', 'Webhook validation failed', [
                'hint' => 'Ensure SQUARE_WEBHOOK_SIGNATURE_KEY matches the signature key from your Square webhook endpoint',
            ]);
        }

        return $isValid;
    }

    /**
     * Check if Square's API is working.
     *
     * Uses the locations API to test connectivity.
     * A successful response or
     * a 4xx error indicates the API is working correctly.
     */
    public function healthCheck(): bool
    {
        try {
            $client = $this->getSquareClient();
            $response = $client->locations->list();

            $errors = $response->getErrors();
            if (! empty($errors)) {
                $error = $errors[0];
                if (in_array($error->getCategory(), ['INVALID_REQUEST_ERROR', 'NOT_FOUND_ERROR'])) {
                    $this->log('info', 'Health check successful (expected 4xx response)');

                    return true;
                }
            }

            return true;
        } catch (SquareApiException $e) {
            if ($e->getStatusCode() >= 400 && $e->getStatusCode() < 500) {
                $this->log('info', 'Health check successful (expected 4xx response)');

                return true;
            }
            $this->log('error', 'Health check failed', ['error' => $e->getMessage()]);

            return false;
        } catch (SquareException $e) {
            $previous = $e->getPrevious();
            if (
                ($previous instanceof ClientException)
                && in_array($previous->getResponse()?->getStatusCode(), [400, 404])
            ) {
                $this->log('info', 'Health check successful (expected 400/404 response)');

                return true;
            }
            $this->log('error', 'Health check failed', ['error' => $e->getMessage()]);

            return false;
        } catch (Throwable $e) {
            $previous = $e->getPrevious();
            if (
                ($e instanceof PaymentException)
                && ($previous instanceof ClientException)
                && in_array($previous->getResponse()?->getStatusCode(), [400, 404])
            ) {
                $this->log('info', 'Health check successful (expected 400/404 response)');

                return true;
            }

            if (str_contains($e->getMessage(), 'NOT_FOUND') || str_contains($e->getMessage(), '404')) {
                $this->log('info', 'Health check successful (expected 4xx response)');

                return true;
            }

            $this->log('error', 'Health check failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Extract payment reference from Square webhook payload.
     * Each provider has different webhook structures - this handles Square's format.
     */
    public function extractWebhookReference(array $payload): ?string
    {
        return $payload['data']['object']['payment']['reference_id']
            ?? $payload['data']['id']
            ?? null;
    }

    /**
     * Extract payment status from Square webhook payload.
     * Returns raw status - StatusNormalizer will convert to standard format.
     */
    public function extractWebhookStatus(array $payload): string
    {
        return $payload['data']['object']['payment']['status']
            ?? $payload['type']
            ?? 'unknown';
    }

    /**
     * Extract payment channel/method from Square webhook payload.
     */
    public function extractWebhookChannel(array $payload): ?string
    {
        return $payload['data']['object']['payment']['source_type'] ?? 'card';
    }

    /**
     * Resolve the ID needed for verification.
     * Square uses payment ID for verification, not the reference.
     */
    public function resolveVerificationId(string $reference, string $providerId): string
    {
        return $providerId;
    }
}
