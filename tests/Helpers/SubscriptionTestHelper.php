<?php

namespace Tests\Helpers;

use KenDeNigerian\PayZephyr\PaymentManager;
use KenDeNigerian\PayZephyr\Subscription;
use ReflectionClass;

class SubscriptionTestHelper
{
    public static function createWithMock(array $responses): Subscription
    {
        $driver = PaystackDriverTestHelper::createWithMock($responses);

        // Create a real PaymentManager and inject the driver using reflection
        $manager = new PaymentManager;
        $reflection = new ReflectionClass($manager);
        $driversProperty = $reflection->getProperty('drivers');
        $driversProperty->setAccessible(true);
        $drivers = $driversProperty->getValue($manager);
        $drivers['paystack'] = $driver;
        $driversProperty->setValue($manager, $drivers);

        // Set default driver in config
        config(['payments.default' => 'paystack']);

        return new Subscription($manager);
    }
}
