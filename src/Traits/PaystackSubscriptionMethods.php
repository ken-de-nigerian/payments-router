<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Traits;

use KenDeNigerian\PayZephyr\DataObjects\SubscriptionPlanDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionRequestDTO;
use KenDeNigerian\PayZephyr\DataObjects\SubscriptionResponseDTO;
use KenDeNigerian\PayZephyr\Exceptions\PlanException;
use KenDeNigerian\PayZephyr\Exceptions\SubscriptionException;
use Throwable;

/**
 * Trait providing Paystack subscription functionality.
 *
 * This trait implements subscription operations for Paystack driver.
 * It follows SRP by separating subscription concerns from payment operations.
 */
trait PaystackSubscriptionMethods
{
    /**
     * Create a subscription plan
     *
     * @return array<string, mixed>
     *
     * @throws PlanException If the plan creation fails
     */
    public function createPlan(SubscriptionPlanDTO $plan): array
    {
        try {
            $response = $this->makeRequest('POST', '/plan', [
                'json' => $plan->toArray(),
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new PlanException(
                    $data['message'] ?? 'Failed to create subscription plan'
                );
            }

            $this->log('info', 'Subscription plan created', [
                'plan_code' => $data['data']['plan_code'] ?? null,
                'name' => $plan->name,
            ]);

            return $data['data'];
        } catch (PlanException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to create plan', [
                'error' => $e->getMessage(),
            ]);
            throw new PlanException(
                'Failed to create plan: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Update a subscription plan
     *
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     *
     * @throws PlanException If the plan update fails
     */
    public function updatePlan(string $planCode, array $updates): array
    {
        try {
            $response = $this->makeRequest('PUT', "/plan/$planCode", [
                'json' => $updates,
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new PlanException(
                    $data['message'] ?? 'Failed to update subscription plan'
                );
            }

            $this->log('info', 'Subscription plan updated', [
                'plan_code' => $planCode,
            ]);

            return $data['data'];
        } catch (PlanException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to update plan', [
                'plan_code' => $planCode,
                'error' => $e->getMessage(),
            ]);
            throw new PlanException(
                'Failed to update plan: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get a subscription plan
     *
     * @return array<string, mixed>
     *
     * @throws PlanException If the plan retrieval fails
     */
    public function getPlan(string $planCode): array
    {
        try {
            $response = $this->makeRequest('GET', "/plan/$planCode");

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new PlanException(
                    $data['message'] ?? 'Failed to get subscription plan'
                );
            }

            return $data['data'];
        } catch (PlanException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to get plan', [
                'plan_code' => $planCode,
                'error' => $e->getMessage(),
            ]);
            throw new PlanException(
                'Failed to get plan: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * List all subscription plans
     *
     * @return array<string, mixed>
     *
     * @throws PlanException If listing plans fails
     */
    public function listPlans(?int $perPage = 50, ?int $page = 1): array
    {
        try {
            $response = $this->makeRequest('GET', '/plan', [
                'query' => [
                    'perPage' => $perPage,
                    'page' => $page,
                ],
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new PlanException(
                    $data['message'] ?? 'Failed to list subscription plans'
                );
            }

            return $data['data'];
        } catch (PlanException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to list plans', [
                'error' => $e->getMessage(),
            ]);
            throw new PlanException(
                'Failed to list plans: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create a subscription
     *
     * @throws SubscriptionException If subscription creation fails
     */
    public function createSubscription(SubscriptionRequestDTO $request): SubscriptionResponseDTO
    {
        try {
            $response = $this->makeRequest('POST', '/subscription', [
                'json' => $request->toArray(),
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new SubscriptionException(
                    $data['message'] ?? 'Failed to create subscription'
                );
            }

            $result = $data['data'];

            $this->log('info', 'Subscription created', [
                'subscription_code' => $result['subscription_code'],
                'customer' => $request->customer,
                'plan' => $request->plan,
            ]);

            return new SubscriptionResponseDTO(
                subscriptionCode: $result['subscription_code'],
                status: $result['status'],
                customer: $result['customer']['email'] ?? $request->customer,
                plan: $result['plan']['name'] ?? $request->plan,
                amount: ($result['amount'] ?? 0) / 100,
                currency: $result['currency'] ?? 'NGN',
                nextPaymentDate: $result['next_payment_date'] ?? null,
                emailToken: $result['email_token'] ?? null,
                metadata: $result['metadata'] ?? [],
                provider: $this->getName(),
            );
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to create subscription', [
                'error' => $e->getMessage(),
            ]);
            throw new SubscriptionException(
                'Failed to create subscription: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get subscription details
     *
     * @throws SubscriptionException If subscription retrieval fails
     */
    public function getSubscription(string $subscriptionCode): SubscriptionResponseDTO
    {
        try {
            $response = $this->makeRequest('GET', "/subscription/$subscriptionCode");

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new SubscriptionException(
                    $data['message'] ?? 'Failed to get subscription'
                );
            }

            $result = $data['data'];

            return new SubscriptionResponseDTO(
                subscriptionCode: $result['subscription_code'] ?? '',
                status: $result['status'] ?? 'unknown',
                customer: $result['customer']['email'] ?? '',
                plan: $result['plan']['name'] ?? '',
                amount: ($result['amount'] ?? 0) / 100,
                currency: $result['currency'] ?? 'NGN',
                nextPaymentDate: $result['next_payment_date'] ?? null,
                emailToken: $result['email_token'] ?? null,
                metadata: $result['metadata'] ?? [],
                provider: $this->getName(),
            );
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to get subscription', [
                'subscription_code' => $subscriptionCode,
                'error' => $e->getMessage(),
            ]);
            throw new SubscriptionException(
                'Failed to get subscription: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Cancel a subscription
     *
     * @throws SubscriptionException If subscription cancellation fails
     */
    public function cancelSubscription(string $subscriptionCode, string $token): SubscriptionResponseDTO
    {
        try {
            $response = $this->makeRequest('POST', '/subscription/disable', [
                'json' => [
                    'code' => $subscriptionCode,
                    'token' => $token,
                ],
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new SubscriptionException(
                    $data['message'] ?? 'Failed to cancel subscription'
                );
            }

            $this->log('info', 'Subscription cancelled', [
                'subscription_code' => $subscriptionCode,
            ]);

            return $this->getSubscription($subscriptionCode);
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to cancel subscription', [
                'subscription_code' => $subscriptionCode,
                'error' => $e->getMessage(),
            ]);
            throw new SubscriptionException(
                'Failed to cancel subscription: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Enable a disabled subscription
     *
     * @throws SubscriptionException If subscription enabling fails
     */
    public function enableSubscription(string $subscriptionCode, string $token): SubscriptionResponseDTO
    {
        try {
            $response = $this->makeRequest('POST', '/subscription/enable', [
                'json' => [
                    'code' => $subscriptionCode,
                    'token' => $token,
                ],
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new SubscriptionException(
                    $data['message'] ?? 'Failed to enable subscription'
                );
            }

            $this->log('info', 'Subscription enabled', [
                'subscription_code' => $subscriptionCode,
            ]);

            return $this->getSubscription($subscriptionCode);
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to enable subscription', [
                'subscription_code' => $subscriptionCode,
                'error' => $e->getMessage(),
            ]);
            throw new SubscriptionException(
                'Failed to enable subscription: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * List customer subscriptions
     *
     * @throws SubscriptionException If listing subscriptions fails
     */
    public function listSubscriptions(?int $perPage = 50, ?int $page = 1, ?string $customer = null): array
    {
        try {
            $query = [
                'perPage' => $perPage,
                'page' => $page,
            ];

            if ($customer) {
                $query['customer'] = $customer;
            }

            $response = $this->makeRequest('GET', '/subscription', [
                'query' => $query,
            ]);

            $data = $this->parseResponse($response);

            if (! ($data['status'] ?? false)) {
                throw new SubscriptionException(
                    $data['message'] ?? 'Failed to list subscriptions'
                );
            }

            return $data['data'];
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log('error', 'Failed to list subscriptions', [
                'error' => $e->getMessage(),
            ]);
            throw new SubscriptionException(
                'Failed to list subscriptions: '.$e->getMessage(),
                0,
                $e
            );
        }
    }
}
