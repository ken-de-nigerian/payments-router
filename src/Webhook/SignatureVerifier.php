<?php
namespace Nwaneri\PaymentsRouter\Webhook;

use Nwaneri\PaymentsRouter\Manager;

class SignatureVerifier
{
    protected Manager $manager;
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function verify(string $provider, array $headers, string $body): bool
    {
        $driver = $this->manager->driver($provider);
        return $driver->validateWebhook($headers, $body);
    }
}
