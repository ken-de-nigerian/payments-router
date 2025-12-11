<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use KenDeNigerian\PayZephyr\Events\WebhookReceived;

test('webhook received event can be dispatched', function () {
    Event::fake();

    WebhookReceived::dispatch('paystack', ['event' => 'charge.success'], 'ref_123');

    Event::assertDispatched(WebhookReceived::class, function ($event) {
        return $event->provider === 'paystack'
            && $event->reference === 'ref_123'
            && $event->payload['event'] === 'charge.success';
    });
});

test('webhook received event has correct properties', function () {
    $event = new WebhookReceived(
        'paystack',
        ['event' => 'charge.success', 'data' => ['reference' => 'ref_123']],
        'ref_123'
    );

    expect($event->provider)->toBe('paystack')
        ->and($event->reference)->toBe('ref_123')
        ->and($event->payload)->toBeArray()
        ->and($event->payload['event'])->toBe('charge.success');
});

test('webhook received event can have null reference', function () {
    $event = new WebhookReceived('paystack', ['event' => 'charge.success'], null);

    expect($event->provider)->toBe('paystack')
        ->and($event->reference)->toBeNull();
});

test('webhook received event is serializable', function () {
    $event = new WebhookReceived(
        'paystack',
        ['event' => 'charge.success'],
        'ref_123'
    );

    // Should be able to serialize for queue
    $serialized = serialize($event);
    $unserialized = unserialize($serialized);

    expect($unserialized->provider)->toBe('paystack')
        ->and($unserialized->reference)->toBe('ref_123');
});
