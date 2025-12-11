<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr;

use Illuminate\Http\RedirectResponse;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponseDTO;
use KenDeNigerian\PayZephyr\DataObjects\VerificationResponseDTO;
use KenDeNigerian\PayZephyr\Exceptions\InvalidConfigurationException;
use KenDeNigerian\PayZephyr\Exceptions\ProviderException;

/**
 * Payment builder for processing payments.
 */
final class Payment
{
    protected PaymentManager $manager;

    protected array $data = [];

    protected array $providers = [];

    public function __construct(PaymentManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Set payment amount.
     */
    public function amount(float $amount): Payment
    {
        $this->data['amount'] = $amount;

        return $this;
    }

    /**
     * Set currency code.
     */
    public function currency(string $currency): Payment
    {
        $this->data['currency'] = strtoupper($currency);

        return $this;
    }

    /**
     * Set customer email.
     */
    public function email(string $email): Payment
    {
        $this->data['email'] = $email;

        return $this;
    }

    /**
     * Set transaction reference.
     */
    public function reference(string $reference): Payment
    {
        $this->data['reference'] = $reference;

        return $this;
    }

    /**
     * Set callback URL (required).
     */
    public function callback(string $url): Payment
    {
        $this->data['callback_url'] = $url;

        return $this;
    }

    /**
     * Set payment metadata.
     */
    public function metadata(array $metadata): Payment
    {
        $this->data['metadata'] = $metadata;

        return $this;
    }

    /**
     * Set idempotency key.
     */
    public function idempotency(string $key): Payment
    {
        $this->data['idempotency_key'] = $key;

        return $this;
    }

    /**
     * Set payment description.
     */
    public function description(string $description): Payment
    {
        $this->data['description'] = $description;

        return $this;
    }

    /**
     * Set customer details.
     */
    public function customer(array $customer): Payment
    {
        $this->data['customer'] = $customer;

        return $this;
    }

    /**
     * Set payment channels.
     */
    public function channels(array $channels): Payment
    {
        $this->data['channels'] = $channels;

        return $this;
    }

    /**
     * Set payment provider(s).
     *
     * @param  string|array<string>  $providers
     */
    public function with(string|array $providers): Payment
    {
        $this->providers = is_array($providers) ? $providers : [$providers];

        return $this;
    }

    /**
     * Alias for with().
     */
    public function using(string|array $providers): Payment
    {
        return $this->with($providers);
    }

    /**
     * Process payment and return response.
     *
     * @throws InvalidConfigurationException
     * @throws ProviderException
     */
    public function charge(): ChargeResponseDTO
    {
        if (empty($this->data['callback_url'] ?? null)) {
            throw new InvalidConfigurationException(
                'Callback URL is required. Please use ->callback(url) in your payment chain.'
            );
        }

        // Use config singleton to avoid breaking config caching
        $config = app('payments.config') ?? config('payments', []);
        $defaultCurrency = $config['currency']['default'] ?? 'NGN';

        $request = ChargeRequestDTO::fromArray(array_merge([
            'currency' => $defaultCurrency,
            'channels' => $this->data['channels'] ?? null,
        ], $this->data));

        return $this->manager->chargeWithFallback($request, $this->providers ?: null);
    }

    /**
     * Process payment and redirect to payment page.
     *
     * @throws ProviderException
     * @throws InvalidConfigurationException
     */
    public function redirect(): RedirectResponse
    {
        $response = $this->charge();

        return redirect()->away($response->authorizationUrl);
    }

    /**
     * Verify payment by reference.
     *
     * @throws ProviderException
     */
    public function verify(string $reference, ?string $provider = null): VerificationResponseDTO
    {
        return $this->manager->verify($reference, $provider);
    }
}
