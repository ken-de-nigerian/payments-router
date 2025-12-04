<?php
namespace Nwaneri\PaymentsRouter;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payments.php', 'payments');

        $this->app->singleton(Manager::class, function ($app) {
            return new Manager($app, $app['config']['payments']);
        });

        $this->app->alias(Manager::class, 'payment.manager');
        $this->app->bind(PaymentRouter::class, function($app){
            return new PaymentRouter($app->make(Manager::class));
        });
    }

    public function boot()
    {
        $this->publishes([__DIR__.'/../config/payments.php' => config_path('payments.php')], 'config');

        // Optional route file - package consumer can publish.
        if (file_exists(__DIR__.'/Routes/webhook.php')) {
            $this->loadRoutesFrom(__DIR__.'/Routes/webhook.php');
        }
    }
}
