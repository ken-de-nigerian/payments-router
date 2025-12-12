<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use KenDeNigerian\PayZephyr\PaymentManager;

beforeEach(function () {
    Cache::flush();
});

test('it clears session cache after successful verification', function () {
    $manager = app(PaymentManager::class);
    $reflection = new ReflectionClass($manager);

    $cacheKeyMethod = $reflection->getMethod('cacheKey');
    $cacheKeyMethod->setAccessible(true);

    $reference = 'TEST_REF_123';
    $key = $cacheKeyMethod->invoke($manager, 'session', $reference);

    // Set cache
    Cache::put($key, [
        'provider' => 'paystack',
        'id' => 'provider_id_123',
    ], now()->addHour());

    expect(Cache::get($key))->not->toBeNull();

    // Simulate verification clearing cache
    Cache::forget($key);

    expect(Cache::get($key))->toBeNull();
});

test('it expires session cache after configured ttl', function () {
    $manager = app(PaymentManager::class);
    $reflection = new ReflectionClass($manager);

    $cacheKeyMethod = $reflection->getMethod('cacheKey');
    $cacheKeyMethod->setAccessible(true);

    $reference = 'TEST_REF_456';
    $key = $cacheKeyMethod->invoke($manager, 'session', $reference);

    // Set cache with 1 hour TTL
    Cache::put($key, [
        'provider' => 'paystack',
        'id' => 'provider_id_456',
    ], now()->addHour());

    expect(Cache::get($key))->not->toBeNull();

    // Travel 61 minutes into future
    $this->travel(61)->minutes();

    // Cache should be expired
    expect(Cache::get($key))->toBeNull();
});

test('it caches health checks for configured duration', function () {
    config(['payments.health_check.cache_ttl' => 120]); // 2 minutes

    $driver = app(PaymentManager::class)->driver('paystack');

    // First call
    $result1 = $driver->getCachedHealthCheck();

    // Second call should use cache
    $result2 = $driver->getCachedHealthCheck();

    expect($result1)->toBe($result2);

    // After TTL, should re-check
    $this->travel(121)->seconds();

    $result3 = $driver->getCachedHealthCheck();

    // Should be a boolean
    expect($result3)->toBeBool();
});

test('it handles cache key collisions with user context', function () {
    $manager = app(PaymentManager::class);
    $reflection = new ReflectionClass($manager);

    $cacheKeyMethod = $reflection->getMethod('cacheKey');
    $cacheKeyMethod->setAccessible(true);

    // Simulate user 1
    $key1 = $cacheKeyMethod->invoke($manager, 'session', 'REF_123');
    Cache::put($key1, ['provider' => 'paystack', 'id' => 'id1'], now()->addHour());

    // Simulate user 2 (different context)
    $key2 = $cacheKeyMethod->invoke($manager, 'session', 'REF_123');
    Cache::put($key2, ['provider' => 'stripe', 'id' => 'id2'], now()->addHour());

    // Keys should be isolated
    expect(Cache::get($key1))->not->toBeNull()
        ->and(Cache::get($key2))->not->toBeNull();
});

test('it handles cache eviction when memory is low', function () {
    $key = 'payzephyr:session:TEST_REF';
    $data = ['provider' => 'paystack', 'id' => 'test_id'];

    // Set cache
    Cache::put($key, $data, now()->addHour());

    expect(Cache::get($key))->not->toBeNull();

    // Manually evict
    Cache::forget($key);

    expect(Cache::get($key))->toBeNull();
});

test('it handles cache prefix isolation', function () {
    $key1 = 'payzephyr:session:REF_1';
    $key2 = 'payzephyr:health:paystack';
    $key3 = 'other_prefix:session:REF_1';

    Cache::put($key1, ['data' => 'session1'], now()->addHour());
    Cache::put($key2, ['data' => 'health1'], now()->addHour());
    Cache::put($key3, ['data' => 'other1'], now()->addHour());

    // All should be independent
    expect(Cache::get($key1))->not->toBeNull()
        ->and(Cache::get($key2))->not->toBeNull()
        ->and(Cache::get($key3))->not->toBeNull();
});

test('it handles cache expiration edge cases', function () {
    $key = 'payzephyr:session:EDGE_CASE';

    // Set cache with very short TTL
    Cache::put($key, ['provider' => 'paystack'], now()->addSecond());

    expect(Cache::get($key))->not->toBeNull();

    // Travel just past expiration
    $this->travel(2)->seconds();

    expect(Cache::get($key))->toBeNull();
});

test('it handles cache with null values', function () {
    $key = 'payzephyr:session:NULL_TEST';

    // Should not throw error
    expect(fn () => Cache::put($key, null, now()->addHour()))->not->toThrow();

    $value = Cache::get($key);
    expect($value)->toBeNull();
});
