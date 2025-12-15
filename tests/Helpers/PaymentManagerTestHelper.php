<?php

namespace Tests\Helpers;

use KenDeNigerian\PayZephyr\Contracts\DriverInterface;
use KenDeNigerian\PayZephyr\PaymentManager;
use Mockery\MockInterface;
use ReflectionClass;

class PaymentManagerTestHelper
{
    public static function withMockDriver(PaymentManager $manager, string $provider, MockInterface $mockDriver): PaymentManager
    {
        $reflection = new ReflectionClass($manager);
        $driversProperty = $reflection->getProperty('drivers');
        $driversProperty->setAccessible(true);
        $drivers = $driversProperty->getValue($manager);
        $drivers[$provider] = $mockDriver;
        $driversProperty->setValue($manager, $drivers);

        return $manager;
    }
}

