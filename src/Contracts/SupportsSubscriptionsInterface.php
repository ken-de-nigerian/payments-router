<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Contracts;

use KenDeNigerian\PayZephyr\DataObjects\SubscriptionPlanDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionResponseDTO;

/**
 * Interface for subscription functionality
 *
 * Not all drivers will support subscriptions - drivers can implement this
 * interface to indicate subscription support.
 */
interface SupportsSubscriptionsInterface
{
    /**
     * Create a subscription plan
     *
     * @return array<string, mixed>
     */
    public function createPlan(SubscriptionPlanDTO $plan): array;

    /**
     * Update a subscription plan
     *
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    public function updatePlan(string $planCode, array $updates): array;

    /**
     * Get a subscription plan
     *
     * @return array<string, mixed>
     */
    public function getPlan(string $planCode): array;

    /**
     * List all subscription plans
     *
     * @return array<string, mixed>
     */
    public function listPlans(?int $perPage = 50, ?int $page = 1): array;

    /**
     * Create a subscription
     */
    public function createSubscription(SubscriptionRequestDTO $request): SubscriptionResponseDTO;

    /**
     * Get subscription details
     */
    public function getSubscription(string $subscriptionCode): SubscriptionResponseDTO;

    /**
     * Cancel a subscription
     *
     * @param  string  $token  Email token (Paystack specific)
     */
    public function cancelSubscription(string $subscriptionCode, string $token): SubscriptionResponseDTO;

    /**
     * Enable a disabled subscription
     */
    public function enableSubscription(string $subscriptionCode, string $token): SubscriptionResponseDTO;

    /**
     * List customer subscriptions
     *
     * @return array<string, mixed>
     */
    public function listSubscriptions(?int $perPage = 50, ?int $page = 1, ?string $customer = null): array;
}
