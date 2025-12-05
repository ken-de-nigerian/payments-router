<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for the PayZephyr package.
 *
 * This provider registers the core PaymentManager singleton, binds the fluent
 * Payment builder, and publishes necessary resources (config, migrations)
 * to the host application.
 */
class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * Merges the package configuration and binds the core classes to the
     * Laravel Service Container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payments.php', 'payments');

        $this->app->singleton(PaymentManager::class);

        $this->app->bind(Payment::class, function ($app) {
            return new Payment($app->make(PaymentManager::class));
        });
    }

    /**
     * Bootstrap the application services.
     *
     * Handles the publishing of configuration and migration files when
     * running via Artisan and registers the webhook routes.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/payments.php' => config_path('payments.php'),
            ], 'payments-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'payments-migrations');
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
    }
}
