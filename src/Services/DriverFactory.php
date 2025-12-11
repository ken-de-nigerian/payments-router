<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Services;

use KenDeNigerian\PayZephyr\Contracts\DriverInterface;
use KenDeNigerian\PayZephyr\Exceptions\DriverNotFoundException;

/**
 * Driver Factory Service
 *
 * This service is responsible for creating driver instances.
 * It follows the Factory pattern and allows registration of custom drivers
 * without modifying core code (OCP compliance).
 *
 * Single Responsibility: Only handles driver instantiation.
 */
final class DriverFactory
{
    /**
     * Registered driver classes.
     * Maps driver names to their class names.
     *
     * @var array<string, string>
     */
    protected array $drivers = [];

    /**
     * Create a driver instance.
     *
     * @param  string  $name  Driver name (e.g., 'paystack', 'square')
     * @param  array  $config  Driver configuration
     *
     * @throws DriverNotFoundException If driver class not found
     */
    public function create(string $name, array $config): DriverInterface
    {
        $class = $this->resolveDriverClass($name);

        if (! class_exists($class)) {
            throw new DriverNotFoundException("Driver class [$class] not found for driver [$name]");
        }

        if (! is_subclass_of($class, DriverInterface::class)) {
            throw new DriverNotFoundException("Driver class [$class] must implement DriverInterface");
        }

        return new $class($config);
    }

    /**
     * Resolve driver class name.
     * Priority: Registered drivers -> Config -> Convention -> Direct class name
     *
     * @param  string  $name  Driver name
     * @return string Fully qualified class name
     */
    protected function resolveDriverClass(string $name): string
    {
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = app('payments.config') ?? config('payments', []);
        $configDriver = $config['providers'][$name]['driver_class'] ?? null;
        if ($configDriver && class_exists($configDriver)) {
            return $configDriver;
        }

        $className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $fqcn = 'KenDeNigerian\PayZephyr\Drivers\\'.$className.'Driver';

        // Try convention-based class name first
        if (class_exists($fqcn)) {
            return $fqcn;
        }

        // Handle special case: PayPal (Paypal -> PayPal)
        if (strtolower($name) === 'paypal' && class_exists('KenDeNigerian\PayZephyr\Drivers\PayPalDriver')) {
            return 'KenDeNigerian\PayZephyr\Drivers\PayPalDriver';
        }

        return $name;
    }

    /**
     * Register a custom driver.
     *
     * This allows extending the package with custom drivers without
     * modifying core code (OCP compliance).
     *
     * @param  string  $name  Driver name (e.g., 'square')
     * @param  string  $class  Fully qualified class name
     * @return $this
     *
     * @throws DriverNotFoundException
     */
    public function register(string $name, string $class): self
    {
        if (! class_exists($class)) {
            throw new DriverNotFoundException("Cannot register driver [$name]: class [$class] does not exist");
        }

        if (! is_subclass_of($class, DriverInterface::class)) {
            throw new DriverNotFoundException("Cannot register driver [$name]: class [$class] must implement DriverInterface");
        }

        $this->drivers[$name] = $class;

        return $this;
    }

    /**
     * Get all registered driver names.
     *
     * @return array<string> Array of driver names
     */
    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Check if a driver is registered.
     *
     * @param  string  $name  Driver name
     */
    public function isRegistered(string $name): bool
    {
        return isset($this->drivers[$name]);
    }
}
