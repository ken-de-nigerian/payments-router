<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr;

use KenDeNigerian\PayZephyr\Contracts\SupportsSubscriptionsInterface;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionPlanDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionResponseDTO;
use KenDeNigerian\PayZephyr\Exceptions\PaymentException;

final class Subscription
{
    protected PaymentManager $manager;

    /** @var array<string, mixed> */
    protected array $data = [];

    /** @var array<int, string> */
    protected array $providers = [];

    protected ?string $subscriptionCode = null;

    protected ?string $planCode = null;

    protected ?string $emailToken = null;

    public function __construct(PaymentManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Set the provider(s) to use
     *
     * @param  string|array<int, string>  $providers
     */
    public function with(string|array $providers): self
    {
        $this->providers = is_array($providers) ? $providers : [$providers];

        return $this;
    }

    /**
     * Alias for with() method
     *
     * @param  string|array<int, string>  $providers
     */
    public function using(string|array $providers): self
    {
        return $this->with($providers);
    }

    /**
     * Set the subscription code (for operations on existing subscriptions)
     */
    public function code(string $subscriptionCode): self
    {
        $this->subscriptionCode = $subscriptionCode;

        return $this;
    }

    /**
     * Set customer email or code
     */
    public function customer(string $customer): self
    {
        $this->data['customer'] = $customer;

        return $this;
    }

    /**
     * Set the plan code (for subscriptions or plan operations)
     */
    public function plan(string $plan): self
    {
        $this->data['plan'] = $plan;
        $this->planCode = $plan;

        return $this;
    }

    /**
     * Set quantity
     */
    public function quantity(int $quantity): self
    {
        $this->data['quantity'] = $quantity;

        return $this;
    }

    /**
     * Set start date
     */
    public function startDate(string $startDate): self
    {
        $this->data['start_date'] = $startDate;

        return $this;
    }

    /**
     * Set trial period in days
     */
    public function trialDays(int $days): self
    {
        $this->data['trial_days'] = $days;

        return $this;
    }

    /**
     * Set authorization code (for card)
     */
    public function authorization(string $authorization): self
    {
        $this->data['authorization'] = $authorization;

        return $this;
    }

    /**
     * Set metadata
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->data['metadata'] = $metadata;

        return $this;
    }

    /**
     * Create a new subscription
     */
    public function create(): SubscriptionResponseDTO
    {
        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        $request = SubscriptionRequestDTO::fromArray($this->data);

        return $driver->createSubscription($request);
    }

    /**
     * Alias for create()
     */
    public function subscribe(): SubscriptionResponseDTO
    {
        return $this->create();
    }

    /**
     * Get subscription details
     */
    public function get(): SubscriptionResponseDTO
    {
        if (! $this->subscriptionCode) {
            throw new PaymentException('Subscription code is required. Use ->code($code)');
        }

        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        return $driver->getSubscription($this->subscriptionCode);
    }

    /**
     * Set email token for cancel/enable operations
     */
    public function token(string $token): self
    {
        $this->emailToken = $token;

        return $this;
    }

    /**
     * Cancel the subscription
     */
    public function cancel(?string $token = null): SubscriptionResponseDTO
    {
        if (! $this->subscriptionCode) {
            throw new PaymentException('Subscription code is required. Use ->code($code)');
        }

        $token = $token ?? $this->emailToken;
        if (! $token) {
            throw new PaymentException('Email token is required. Use ->token($token) or pass as parameter');
        }

        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        return $driver->cancelSubscription($this->subscriptionCode, $token);
    }

    /**
     * Enable a cancelled subscription
     */
    public function enable(?string $token = null): SubscriptionResponseDTO
    {
        if (! $this->subscriptionCode) {
            throw new PaymentException('Subscription code is required. Use ->code($code)');
        }

        $token = $token ?? $this->emailToken;
        if (! $token) {
            throw new PaymentException('Email token is required. Use ->token($token) or pass as parameter');
        }

        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        return $driver->enableSubscription($this->subscriptionCode, $token);
    }

    /**
     * Set pagination parameters
     */
    public function perPage(int $perPage): self
    {
        $this->data['per_page'] = $perPage;

        return $this;
    }

    /**
     * Set page number
     */
    public function page(int $page): self
    {
        $this->data['page'] = $page;

        return $this;
    }

    /**
     * List subscriptions
     *
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function list(?string $customer = null): array
    {
        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        $perPage = $this->data['per_page'] ?? 50;
        $page = $this->data['page'] ?? 1;

        return $driver->listSubscriptions($perPage, $page, $customer);
    }

    /**
     * Set plan data for creation
     */
    public function planData(SubscriptionPlanDTO $plan): self
    {
        $this->data['plan_data'] = $plan;

        return $this;
    }

    /**
     * Set plan updates
     *
     * @param  array<string, mixed>  $updates
     */
    public function planUpdates(array $updates): self
    {
        $this->data['plan_updates'] = $updates;

        return $this;
    }

    /**
     * Create a subscription plan
     *
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function createPlan(): array
    {
        if (! isset($this->data['plan_data'])) {
            throw new PaymentException('Plan data is required. Use ->planData($planDTO)');
        }

        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        return $driver->createPlan($this->data['plan_data']);
    }

    /**
     * Update a subscription plan
     *
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function updatePlan(): array
    {
        if (! $this->planCode) {
            throw new PaymentException('Plan code is required. Use ->plan($planCode)');
        }

        if (! isset($this->data['plan_updates'])) {
            throw new PaymentException('Plan updates are required. Use ->planUpdates($updates)');
        }

        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        return $driver->updatePlan($this->planCode, $this->data['plan_updates']);
    }

    /**
     * Get a subscription plan
     *
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function getPlan(): array
    {
        if (! $this->planCode) {
            throw new PaymentException('Plan code is required. Use ->plan($planCode)');
        }

        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        return $driver->getPlan($this->planCode);
    }

    /**
     * List subscription plans
     *
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function listPlans(): array
    {
        $driver = $this->getDriver();

        if (! ($driver instanceof SupportsSubscriptionsInterface)) {
            throw new PaymentException(
                "Provider [{$this->getProviderName()}] does not support subscriptions"
            );
        }

        $perPage = $this->data['per_page'] ?? 50;
        $page = $this->data['page'] ?? 1;

        return $driver->listPlans($perPage, $page);
    }

    protected function getDriver(): Contracts\DriverInterface
    {
        $providerName = $this->getProviderName();

        return $this->manager->driver($providerName);
    }

    protected function getProviderName(): string
    {
        if (! empty($this->providers)) {
            return $this->providers[0];
        }

        return $this->manager->getDefaultDriver();
    }
}
