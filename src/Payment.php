<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr;

use Illuminate\Http\RedirectResponse;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequest;
use KenDeNigerian\PayZephyr\DataObjects\ChargeResponse;
use KenDeNigerian\PayZephyr\Exceptions\ProviderException;

/**
 * Class Payment
 *
 * Fluent API for payment operations
 */
class Payment
{
    protected PaymentManager $manager;
    protected array $data = [];
    protected array $providers = [];

    public function __construct(PaymentManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Set payment amount
     */
    public function amount(float $amount): static
    {
        $this->data['amount'] = $amount;
        return $this;
    }

    /**
     * Set currency
     */
    public function currency(string $currency): static
    {
        $this->data['currency'] = strtoupper($currency);
        return $this;
    }

    /**
     * Set customer email
     */
    public function email(string $email): static
    {
        $this->data['email'] = $email;
        return $this;
    }

    /**
     * Set payment reference
     */
    public function reference(string $reference): static
    {
        $this->data['reference'] = $reference;
        return $this;
    }

    /**
     * Set callback URL
     */
    public function callback(string $url): static
    {
        $this->data['callback_url'] = $url;
        return $this;
    }

    /**
     * Set metadata
     */
    public function metadata(array $metadata): static
    {
        $this->data['metadata'] = $metadata;
        return $this;
    }

    /**
     * Set description
     */
    public function description(string $description): static
    {
        $this->data['description'] = $description;
        return $this;
    }

    /**
     * Set customer data
     */
    public function customer(array $customer): static
    {
        $this->data['customer'] = $customer;
        return $this;
    }

    /**
     * Specify provider(s) to use
     */
    public function with(string|array $providers): static
    {
        $this->providers = is_array($providers) ? $providers : [$providers];
        return $this;
    }

    /**
     * Alias for with()
     */
    public function using(string|array $providers): static
    {
        return $this->with($providers);
    }

    /**
     * Create charge and get response
     * @throws ProviderException
     */
    public function charge(): ChargeResponse
    {
        $request = ChargeRequest::fromArray(array_merge([
            'currency' => config('payments.currency.default', 'NGN'),
        ], $this->data));

        return $this->manager->chargeWithFallback($request, $this->providers ?: null);
    }

    /**
     * Create charge and redirect
     * @throws ProviderException
     */
    public function redirect(): RedirectResponse
    {
        $response = $this->charge();
        return redirect()->away($response->authorizationUrl);
    }

    /**
     * Verify a payment
     * @throws ProviderException
     */
    public function verify(string $reference, ?string $provider = null): DataObjects\VerificationResponse
    {
        return $this->manager->verify($reference, $provider);
    }
}
