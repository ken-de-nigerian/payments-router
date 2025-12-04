<?php
namespace KenDeNigerian\PayZephyr\Webhook;

use KenDeNigerian\PayZephyr\Manager;

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
