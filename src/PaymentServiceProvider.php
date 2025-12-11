<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use KenDeNigerian\PayZephyr\Console\InstallCommand;
use KenDeNigerian\PayZephyr\Contracts\ChannelMapperInterface;
use KenDeNigerian\PayZephyr\Contracts\ProviderDetectorInterface;
use KenDeNigerian\PayZephyr\Contracts\StatusNormalizerInterface;
use KenDeNigerian\PayZephyr\Http\Controllers\WebhookController;
use KenDeNigerian\PayZephyr\Models\PaymentTransaction;
use KenDeNigerian\PayZephyr\Services\ChannelMapper;
use KenDeNigerian\PayZephyr\Services\DriverFactory;
use KenDeNigerian\PayZephyr\Services\ProviderDetector;
use KenDeNigerian\PayZephyr\Services\StatusNormalizer;
use Throwable;

/**
 * Service Provider for PayZephyr package.
 */
final class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payments.php', 'payments');

        // Register configuration as a singleton to avoid config() calls that break caching
        $this->app->singleton('payments.config', fn () => config('payments'));

        // Register services using interfaces for better testability and dependency injection
        $this->app->singleton(StatusNormalizerInterface::class, StatusNormalizer::class);
        $this->app->singleton(ProviderDetectorInterface::class, ProviderDetector::class);
        $this->app->singleton(ChannelMapperInterface::class, ChannelMapper::class);

        // Also bind concrete classes for backward compatibility
        $this->app->singleton(StatusNormalizer::class);
        $this->app->singleton(ProviderDetector::class);
        $this->app->singleton(ChannelMapper::class);

        // Register DriverFactory
        $this->app->singleton(DriverFactory::class);

        // Register PaymentManager with explicit closure for better control
        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager(
                $app->make(ProviderDetectorInterface::class),
                $app->make(DriverFactory::class)
            );
        });

        // Register Payment builder
        $this->app->bind(Payment::class, function ($app) {
            return new Payment($app->make(PaymentManager::class));
        });
    }

    /**
     * Bootstrap application services.
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

            // Register Artisan commands
            $this->commands([
                InstallCommand::class,
            ]);
        }

        // Register routes in boot method with proper configuration
        $this->registerRoutes();

        // Set table name for PaymentTransaction model
        $this->configureModel();

        // Register webhook-specific status mappings (OCP: extensible without modification)
        $this->registerWebhookStatusMappings();
    }

    /**
     * Register routes.
     */
    protected function registerRoutes(): void
    {
        if (! $this->app->routesAreCached()) {
            $config = app('payments.config') ?? config('payments', []);
            $webhookPath = $config['webhook']['path'] ?? '/payments/webhook';
            $rateLimit = $config['webhook']['rate_limit'] ?? '120,1';

            Route::group([
                'prefix' => $webhookPath,
                'middleware' => ['api', 'throttle:'.$rateLimit],
                'namespace' => 'KenDeNigerian\PayZephyr\Http\Controllers',
            ], function () {
                Route::post('/{provider}', [WebhookController::class, 'handle'])
                    ->name('payments.webhook');
            });

            // Health check endpoint
            Route::get('/payments/health', function (PaymentManager $manager) {
                $providers = [];
                $healthConfig = app('payments.config') ?? config('payments', []);

                foreach ($healthConfig['providers'] ?? [] as $name => $providerConfig) {
                    if ($providerConfig['enabled'] ?? false) {
                        try {
                            $driver = $manager->driver($name);
                            $providers[$name] = [
                                'healthy' => $driver->getCachedHealthCheck(),
                                'currencies' => $driver->getSupportedCurrencies(),
                            ];
                        } catch (Throwable $e) {
                            $providers[$name] = [
                                'healthy' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                }

                return response()->json([
                    'status' => 'operational',
                    'providers' => $providers,
                ]);
            })->middleware('api')->name('payments.health');
        }
    }

    /**
     * Configure model table name.
     */
    protected function configureModel(): void
    {
        $config = app('payments.config') ?? config('payments', []);
        $tableName = $config['logging']['table'] ?? 'payment_transactions';
        PaymentTransaction::setTableName($tableName);
    }

    /**
     * Register webhook status mappings.
     */
    protected function registerWebhookStatusMappings(): void
    {
        $normalizer = $this->app->make(StatusNormalizerInterface::class);

        // PayPal webhook event types
        $normalizer->registerProviderMappings('paypal', [
            'success' => ['PAYMENT.CAPTURE.COMPLETED', 'COMPLETED'],
            'failed' => ['PAYMENT.CAPTURE.DENIED'],
        ]);
    }
}
