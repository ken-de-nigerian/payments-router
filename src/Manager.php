<?php
namespace Nwaneri\PaymentsRouter;

use Illuminate\Contracts\Foundation\Application;
use Nwaneri\PaymentsRouter\Contracts\DriverInterface;
use Nwaneri\PaymentsRouter\Exceptions\PaymentException;

class Manager
{
    public Application $app;
    public array $config;
    protected array $drivers = [];
    protected ?string $default;

    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
        $this->default = $config['default'] ?? null;
    }

    public function driver(string $name): DriverInterface
    {
        if (isset($this->drivers[$name])) return $this->drivers[$name];

        $provider = $this->config['providers'][$name] ?? null;
        if (!$provider) {
            throw new PaymentException("Unknown payment provider [{$name}]");
        }

        $class = $provider['driver'] ?? null;
        if (!class_exists($class)) {
            throw new PaymentException("Driver class [{$class}] not found for provider [{$name}]");
        }

        $this->drivers[$name] = new $class($provider);
        return $this->drivers[$name];
    }

    public function defaultDriver(): string
    {
        return $this->default ?: array_key_first($this->config['providers']);
    }

    public function attemptAcrossProviders(array $preferredList, callable $operation, ?string &$usedProvider = null)
    {
        $providers = $preferredList ?: [$this->defaultDriver(), $this->config['fallback'] ?? null];
        $providers = array_filter($providers);

        foreach ($providers as $p) {
            try {
                $driver = $this->driver($p);
                if ($this->config['health_check']['enabled'] ?? false) {
                    if (!$driver->healthCheck()) {
                        continue;
                    }
                }
                $usedProvider = $p;
                return $operation($driver);
            } catch (\Throwable $e) {
                logger()->error("Payment provider [{$p}] failed: " . $e->getMessage());
                continue;
            }
        }

        throw new PaymentException('All payment providers failed.');
    }
}
