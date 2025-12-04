<?php

namespace KenDeNigerian\PayZephyr\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use KenDeNigerian\PayZephyr\PaymentServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PaymentServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('payments.default', 'paystack');
        $app['config']->set('payments.providers.paystack', [
            'driver' => 'paystack',
            'secret_key' => 'sk_test_xxx',
            'public_key' => 'pk_test_xxx',
            'enabled' => true,
            'currencies' => ['NGN', 'USD'],
        ]);
    }
}
