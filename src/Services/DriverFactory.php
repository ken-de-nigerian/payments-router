<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Services;

use KenDeNigerian\PayZephyr\Contracts\DriverInterface;
use KenDeNigerian\PayZephyr\Exceptions\DriverNotFoundException;

final class DriverFactory
{
    /** @var array<string, string> */
    protected array $drivers = [];

    /**
     * @throws DriverNotFoundException
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

        if (class_exists($fqcn)) {
            return $fqcn;
        }

        if (strtolower($name) === 'paypal' && class_exists('KenDeNigerian\PayZephyr\Drivers\PayPalDriver')) {
            return 'KenDeNigerian\PayZephyr\Drivers\PayPalDriver';
        }

        return $name;
    }

    /**
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

    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }

    public function isRegistered(string $name): bool
    {
        return isset($this->drivers[$name]);
    }
}
