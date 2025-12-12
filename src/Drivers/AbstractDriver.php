<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Cache;
use KenDeNigerian\PayZephyr\Contracts\DriverInterface;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\Exceptions\ChargeException;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Services\ChannelMapper;
use KenDeNigerian\PayZephyr\Services\StatusNormalizer;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * AbstractDriver - Base Class for All Payment Providers
 *
 * This is the parent class that all payment provider drivers extend.
 * It provides common functionality like HTTP requests, health checks,
 * currency validation, and reference generation.
 */
abstract class AbstractDriver implements DriverInterface
{
    protected Client $client;

    protected array $config;

    protected string $name;

    /**
     * The payment request currently being processed.
     * Used to access the idempotency key when making API requests.
     */
    protected ?ChargeRequestDTO $currentRequest = null;

    /**
     * Status normalizer instance.
     * Can be injected for testing or to use custom normalizer.
     */
    protected ?StatusNormalizer $statusNormalizer = null;

    /**
     * Channel mapper instance.
     * Can be injected for testing or to use custom mapper.
     */
    protected ?ChannelMapper $channelMapper = null;

    /**
     * Sensitive keys to redact from logs
     */
    protected array $sensitiveKeys = [
        'password',
        'secret',
        'token',
        'api_key',
        'access_token',
        'refresh_token',
        'card_number',
        'cvv',
        'pin',
        'ssn',
        'account_number',
        'routing_number',
    ];

    /**
     * Create a new payment driver instance.
     *
     * @throws InvalidConfigurationException If required, config is missing.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->validateConfig();
        $this->initializeClient();
    }

    /**
     * Check that all required configuration is present (API keys, etc.).
     * Each driver implements this to check for their specific requirements.
     *
     * @throws InvalidConfigurationException If something is missing.
     */
    abstract protected function validateConfig(): void;

    /**
     * Set up the HTTP client for making API requests to the payment provider.
     */
    protected function initializeClient(): void
    {
        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? '',
            'timeout' => $this->config['timeout'] ?? 30,
            'verify' => ! ($this->config['testing_mode'] ?? false),
            'headers' => $this->getDefaultHeaders(),
        ]);
    }

    /**
     * Get the default HTTP headers needed for API requests (like Authorization).
     * Each driver implements this with their provider's specific headers.
     */
    abstract protected function getDefaultHeaders(): array;

    /**
     * Make an HTTP request to the payment provider's API.
     *
     * Automatically adds the idempotency key header if one was provided,
     * which prevents accidentally charging the same payment twice.
     *
     * Network errors are caught and wrapped with more user-friendly messages
     * to prevent crashes and provide better error context.
     *
     * @throws ChargeException If the HTTP request fails.
     */
    protected function makeRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        if ($this->currentRequest?->idempotencyKey) {
            $idempotencyHeaders = $this->getIdempotencyHeader($this->currentRequest->idempotencyKey);

            $headersAlreadySet = false;
            foreach (array_keys($idempotencyHeaders) as $headerName) {
                if (isset($options['headers'][$headerName])) {
                    $headersAlreadySet = true;
                    break;
                }
            }

            if (! $headersAlreadySet) {
                $options['headers'] = array_merge(
                    $options['headers'] ?? [],
                    $idempotencyHeaders
                );
            }
        }

        try {
            return $this->client->request($method, $uri, $options);
        } catch (GuzzleException $e) {

            $this->handleNetworkError($e, $method, $uri);

            $context = [
                'method' => $method,
                'uri' => $uri,
                'provider' => $this->getName(),
            ];

            throw ChargeException::withContext(
                $this->getNetworkErrorMessage($e),
                $context,
                $e
            );
        }
    }

    /**
     * Handle network errors gracefully with better logging and context.
     *
     * This method distinguishes between different types of network errors
     * and provides user-friendly error messages and logging.
     *
     * @param  GuzzleException  $exception  The network exception that occurred
     * @param  string  $method  HTTP method that was attempted
     * @param  string  $uri  URI that was requested
     */
    protected function handleNetworkError(GuzzleException $exception, string $method, string $uri): void
    {
        $errorType = 'network_error';
        $userMessage = 'Network error occurred while communicating with payment provider';
        $context = [
            'method' => $method,
            'uri' => $uri,
            'provider' => $this->getName(),
            'error_class' => get_class($exception),
        ];

        if ($exception instanceof ConnectException) {
            $errorType = 'connection_error';
            $userMessage = 'Unable to connect to payment provider. Please check your internet connection and try again.';
            $context['error_type'] = 'connection_failure';
            $context['hint'] = 'This could be due to network timeout, DNS resolution failure, or the payment provider being temporarily unavailable.';
        } elseif ($exception instanceof ServerException) {
            $errorType = 'server_error';
            $userMessage = 'Payment provider server error. Please try again later.';
            $response = $exception->getResponse();
            $context['status_code'] = $response->getStatusCode();
            $context['response_body'] = (string) $response->getBody();
        } elseif ($exception instanceof RequestException) {
            $errorType = 'request_error';
            $userMessage = 'Request to payment provider failed. Please check your request and try again.';
            $response = $exception->getResponse();
            if ($response !== null) {
                $context['status_code'] = $response->getStatusCode();
            }
        } elseif ($exception instanceof TransferException) {
            $errorType = 'transfer_error';
            $userMessage = 'Data transfer error occurred. Please try again.';
        }

        $this->log('error', "Network error during $method request to $uri", array_merge($context, [
            'error_message' => $exception->getMessage(),
            'error_type' => $errorType,
            'user_message' => $userMessage,
        ]));
    }

    /**
     * Get a user-friendly error message from a GuzzleException.
     *
     * This method provides better error messages for different types of network errors,
     * making it easier for users to understand what went wrong.
     *
     * @param  GuzzleException  $exception  The network exception
     * @return string User-friendly error message
     */
    protected function getNetworkErrorMessage(GuzzleException $exception): string
    {
        if ($exception instanceof ConnectException) {
            return 'Unable to connect to payment provider. This may be due to a network timeout, connection issue, or the provider being temporarily unavailable. Please try again.';
        }

        if ($exception instanceof ServerException) {
            $response = $exception->getResponse();
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 500) {
                return 'Payment provider server error. The provider is experiencing issues. Please try again later.';
            }
        }

        if ($exception instanceof RequestException) {
            $response = $exception->getResponse();
            if ($response !== null) {
                $statusCode = $response->getStatusCode();
                if ($statusCode === 429) {
                    return 'Too many requests. Please wait a moment and try again.';
                }
                if ($statusCode >= 400 && $statusCode < 500) {
                    return 'Invalid request to payment provider. Please check your payment details and try again.';
                }
            }
        }

        return 'Network error occurred while processing payment. Please check your connection and try again.';
    }

    /**
     * Get the HTTP header name and value for idempotency.
     * Most providers use 'Idempotency-Key', but some might use different names.
     * Override this in specific drivers if needed.
     */
    protected function getIdempotencyHeader(string $key): array
    {
        return ['Idempotency-Key' => $key];
    }

    /**
     * Store the current payment request, so we can access it later (for idempotency keys).
     */
    protected function setCurrentRequest(ChargeRequestDTO $request): void
    {
        $this->currentRequest = $request;
    }

    /**
     * Clear the stored request (cleanup after processing).
     */
    protected function clearCurrentRequest(): void
    {
        $this->currentRequest = null;
    }

    /**
     * Convert the HTTP response body from JSON to a PHP array.
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        return json_decode($body, true) ?? [];
    }

    /**
     * Get the name of this payment provider (e.g., 'paystack', 'stripe').
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the list of currencies this provider supports (e.g., ['NGN', 'USD', 'EUR']).
     */
    public function getSupportedCurrencies(): array
    {
        return $this->config['currencies'] ?? [];
    }

    /**
     * Create a unique transaction reference (like 'PAYSTACK_1234567890_abc123def456').
     *
     * Format: PREFIX_TIMESTAMP_RANDOMHEX
     *
     * @param  string|null  $prefix  Custom prefix (defaults to provider name in uppercase)
     *
     * @throws RandomException If random number generation fails.
     */
    protected function generateReference(?string $prefix = null): string
    {
        $prefix = $prefix ?? strtoupper($this->getName());

        return $prefix.'_'.time().'_'.bin2hex(random_bytes(8));
    }

    /**
     * Check if this provider supports a specific currency (e.g., 'NGN', 'USD').
     */
    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }

    /**
     * Check if the provider is working (cached result).
     *
     * The result is cached for a few minutes, so we don't check too often.
     * This prevents slowing down payments with repeated health checks.
     */
    public function getCachedHealthCheck(): bool
    {
        $cacheKey = 'payments.health.'.$this->getName();
        $config = app('payments.config') ?? config('payments', []);
        $cacheTtl = $config['health_check']['cache_ttl'] ?? 300;

        return Cache::remember($cacheKey, $cacheTtl, function () {
            return $this->healthCheck();
        });
    }

    /**
     * Write a log message (for debugging and monitoring).
     *
     * @param  string  $level  Log level: 'info', 'warning', 'error', etc.
     * @param  string  $message  The log message
     * @param  array  $context  Extra data to include in the log
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $config = app('payments.config') ?? config('payments', []);
        if (! ($config['logging']['enabled'] ?? true)) {
            return;
        }

        // Sanitize context before logging
        $sanitizedContext = $this->sanitizeLogContext($context);

        logger()->{$level}("[{$this->getName()}] $message", $sanitizedContext);
    }

    /**
     * Recursively sanitize log context
     */
    protected function sanitizeLogContext(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if ($this->isSensitiveKey($key)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = $this->sanitizeLogContext($value);
                }
            }

            return $sanitized;
        }

        if (is_object($data)) {
            // Handle objects by converting to array first
            $array = method_exists($data, 'toArray')
                ? $data->toArray()
                : (array) $data;

            return $this->sanitizeLogContext($array);
        }

        // Sanitize strings that look like API keys or tokens
        if (is_string($data) && strlen($data) > 20) {
            if (preg_match('/^(sk_|pk_|whsec_|Bearer\s+)/i', $data)) {
                return '[REDACTED_TOKEN]';
            }
        }

        return $data;
    }

    /**
     * Check if a key is considered sensitive
     */
    protected function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (str_contains($key, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper to append a query parameter to a URL.
     * Handles cases where the URL already has query params.
     */
    protected function appendQueryParam(?string $url, string $key, string $value): ?string
    {
        if (! $url) {
            return null;
        }

        $separator = parse_url($url, PHP_URL_QUERY) ? '&' : '?';

        return "$url$separator$key=$value";
    }

    /**
     * Replace the HTTP client (mainly used for testing with mock clients).
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Normalize provider-specific status values to internal standard statuses.
     *
     * This method delegates to the StatusNormalizer service, which can be
     * extended without modifying this class (OCP compliance).
     *
     * Drivers can override this method to provide custom normalization logic,
     * or they can register provider-specific mappings with the normalizer.
     *
     * @param  string  $status  The provider-specific status value
     * @return string Normalized status value
     */
    protected function normalizeStatus(string $status): string
    {
        return $this->getStatusNormalizer()->normalize($status, $this->getName());
    }

    /**
     * Get the status normalizer instance.
     * Uses dependency injection if available, otherwise creates a new instance.
     */
    protected function getStatusNormalizer(): StatusNormalizer
    {
        if ($this->statusNormalizer === null) {
            $this->statusNormalizer = app(StatusNormalizer::class);
        }

        return $this->statusNormalizer;
    }

    /**
     * Set a custom status normalizer (mainly for testing).
     *
     * @return $this
     */
    public function setStatusNormalizer(StatusNormalizer $normalizer): self
    {
        $this->statusNormalizer = $normalizer;

        return $this;
    }

    /**
     * Get the channel mapper instance.
     * Uses dependency injection if available, otherwise creates a new instance.
     */
    protected function getChannelMapper(): ChannelMapper
    {
        if ($this->channelMapper === null) {
            $this->channelMapper = app(ChannelMapper::class);
        }

        return $this->channelMapper;
    }

    /**
     * Set a custom channel mapper (mainly for testing).
     *
     * @return $this
     */
    public function setChannelMapper(ChannelMapper $mapper): self
    {
        $this->channelMapper = $mapper;

        return $this;
    }

    /**
     * Map unified channels to a provider-specific format.
     * If no channels are provided, returns null (provider uses its defaults).
     * Only returns default channels if explicitly needed by the provider.
     *
     * @param  ChargeRequestDTO  $request  The payment request
     * @return array<string>|null Provider-specific channels or null if not applicable
     */
    protected function mapChannels(ChargeRequestDTO $request): ?array
    {
        $mapper = $this->getChannelMapper();

        if (! $mapper->supportsChannels($this->getName())) {
            return null;
        }

        if (! empty($request->channels)) {
            return $mapper->mapChannels($request->channels, $this->getName());
        }

        return null;
    }

    /**
     * Validate webhook timestamp to prevent replay attacks
     *
     * @param  array  $payload  Webhook payload
     * @param  int  $toleranceSeconds  Allowed time difference (default: 300 = 5 minutes)
     */
    protected function validateWebhookTimestamp(array $payload, int $toleranceSeconds = 300): bool
    {
        $timestamp = $this->extractWebhookTimestamp($payload);

        if ($timestamp === null) {
            $this->log('warning', 'Webhook timestamp missing', [
                'hint' => 'Consider rejecting webhooks without timestamps to prevent replay attacks',
            ]);

            return true;
        }

        $currentTime = time();
        $timeDifference = abs($currentTime - $timestamp);

        if ($timeDifference > $toleranceSeconds) {
            $this->log('warning', 'Webhook timestamp outside tolerance window', [
                'timestamp' => $timestamp,
                'current_time' => $currentTime,
                'difference_seconds' => $timeDifference,
                'tolerance_seconds' => $toleranceSeconds,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Extract timestamp from webhook payload
     * Override in specific drivers if needed
     *
     * @return int|null Unix timestamp
     */
    protected function extractWebhookTimestamp(array $payload): ?int
    {
        $timestampFields = [
            'timestamp',
            'created_at',
            'createdAt',
            'event_time',
            'eventTime',
            'time',
        ];

        foreach ($timestampFields as $field) {
            if (isset($payload[$field])) {
                $value = $payload[$field];

                if (is_string($value) && strtotime($value) !== false) {
                    return strtotime($value);
                }

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        }

        return null;
    }

    /**
     * Get the transaction reference from a raw webhook payload.
     * Default implementation that can be overridden by specific drivers.
     */
    public function extractWebhookReference(array $payload): ?string
    {
        return $payload['reference'] ?? $payload['transactionReference'] ?? null;
    }

    /**
     * Get the payment status from a raw webhook payload (in provider-native format).
     * The normalizer will take care of converting this to standard format.
     * Default implementation that can be overridden by specific drivers.
     */
    public function extractWebhookStatus(array $payload): string
    {
        return $payload['status'] ?? $payload['paymentStatus'] ?? 'unknown';
    }

    /**
     * Get the payment channel (e.g., 'card', 'bank_transfer') from a raw webhook payload.
     * Default implementation that can be overridden by specific drivers.
     */
    public function extractWebhookChannel(array $payload): ?string
    {
        return $payload['channel'] ?? $payload['paymentMethod'] ?? null;
    }

    /**
     * Resolve the actual ID needed for verification (which may differ from the
     * internal reference or the provider's Access Code).
     * Default implementation uses the provider's internal ID.
     *
     * @param  string  $reference  The package's unique reference (e.g., PAYSTACK_...)
     * @param  string  $providerId  The provider's internal ID saved during charge (e.g., Paystack access_code)
     */
    public function resolveVerificationId(string $reference, string $providerId): string
    {
        return $providerId;
    }
}
