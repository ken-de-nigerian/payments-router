<?php
namespace Nwaneri\PaymentsRouter;

use Nwaneri\PaymentsRouter\Exceptions\PaymentException;

class PaymentRouter
{
    protected Manager $manager;
    protected array $payload = [];
    protected array $preferred = [];

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public static function amount(int $amount)
    {
        $instance = app(self::class);
        $instance->payload['amount'] = $amount;
        return $instance;
    }

    public function using(string $provider)
    {
        $this->preferred = [$provider];
        return $this;
    }

    public function with(string $provider)
    {
        return $this->using($provider);
    }

    public function currency(string $currency)
    {
        $this->payload['currency'] = strtoupper($currency);
        return $this;
    }

    public function email(string $email)
    {
        $this->payload['email'] = $email;
        return $this;
    }

    public function metadata(array $meta)
    {
        $this->payload['metadata'] = $meta;
        return $this;
    }

    public function callback(string $url)
    {
        $this->payload['callback_url'] = $url;
        return $this;
    }

    public function redirect()
    {
        $used = null;
        $response = $this->manager->attemptAcrossProviders($this->preferred, function($driver) {
            return $driver->createCharge($this->payload);
        }, $used);

        $driver = $this->manager->driver($used);
        return $driver->redirectResponse($response);
    }

    public function create()
    {
        $used = null;
        return $this->manager->attemptAcrossProviders($this->preferred, function($driver){
            return $driver->createCharge($this->payload);
        }, $used);
    }

    public function verify(string $reference)
    {
        $providers = array_keys($this->manager->config['providers'] ?? []);
        $lastException = null;
        foreach ($providers as $p) {
            try {
                $driver = $this->manager->driver($p);
                return $driver->verifyPayment($reference);
            } catch (\Throwable $e) {
                $lastException = $e;
                continue;
            }
        }
        throw new PaymentException('Unable to verify reference: ' . ($lastException?->getMessage() ?? 'unknown'));
    }
}
