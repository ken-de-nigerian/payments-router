<?php

declare(strict_types=1);

use KenDeNigerian\PayZephyr\Contracts\ChannelMapperInterface;
use KenDeNigerian\PayZephyr\Contracts\ProviderDetectorInterface;
use KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface;
use KenDeNigerian\PayZephyr\Services\ChannelMapper;
use KenDeNigerian\PayZephyr\Services\ProviderDetector;
use KenDeNigerian\PayZephyr\Services\StatusNormalizer;

test('status normalizer interface is bound correctly', function () {
    $normalizer = app(StatusNormalizerInterface::class);

    expect($normalizer)->toBeInstanceOf(StatusNormalizer::class)
        ->and($normalizer)->toBeInstanceOf(StatusNormalizerInterface::class);
});

test('provider detector interface is bound correctly', function () {
    $detector = app(ProviderDetectorInterface::class);

    expect($detector)->toBeInstanceOf(ProviderDetector::class)
        ->and($detector)->toBeInstanceOf(ProviderDetectorInterface::class);
});

test('channel mapper interface is bound correctly', function () {
    $mapper = app(ChannelMapperInterface::class);

    expect($mapper)->toBeInstanceOf(ChannelMapper::class)
        ->and($mapper)->toBeInstanceOf(ChannelMapperInterface::class);
});

test('interfaces can be used for dependency injection', function () {
    $normalizer = app(StatusNormalizerInterface::class);
    $result = $normalizer->normalize('SUCCESS');

    expect($result)->toBe('success');
});

test('concrete classes are still bound for backward compatibility', function () {
    $normalizer = app(StatusNormalizer::class);
    $detector = app(ProviderDetector::class);
    $mapper = app(ChannelMapper::class);

    expect($normalizer)->toBeInstanceOf(StatusNormalizer::class)
        ->and($detector)->toBeInstanceOf(ProviderDetector::class)
        ->and($mapper)->toBeInstanceOf(ChannelMapper::class);
});

test('interfaces return same instance as concrete classes (singleton)', function () {
    $interfaceNormalizer = app(StatusNormalizerInterface::class);
    $concreteNormalizer = app(StatusNormalizer::class);

    // They should be the same instance since both are singletons
    expect($interfaceNormalizer)->toBe($concreteNormalizer);
});
