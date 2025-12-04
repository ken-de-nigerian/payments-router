<?php
use Orchestra\Testbench\TestCase;
use Nwaneri\PaymentsRouter\PaymentServiceProvider;
use Nwaneri\PaymentsRouter\Drivers\PaystackDriver;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class PaymentRouterTest extends TestCase
{
    protected function getPackageProviders($app) {
        return [PaymentServiceProvider::class];
    }

    public function testFacadeExists()
    {
        $this->assertTrue(class_exists(\Nwaneri\PaymentsRouter\Facades\Payment::class));
    }

    public function testPaystackCreateChargeMocked()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => true, 'data' => ['authorization_url' => 'https://paystack.test/checkout', 'reference' => 'ref_123']])),
        ]);
        $stack = HandlerStack::create($mock);
        $client = new Client(['handler' => $stack]);

        $config = [
            'secret' => 'sk_test',
            'base_url' => 'https://api.paystack.co',
        ];

        // inject the mocked client into PaystackDriver by extending class (simple approach)
        $driver = new PaystackDriver($config);
        // override http client for testing
        $reflection = new ReflectionClass($driver);
        $prop = $reflection->getProperty('http');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        $res = $driver->createCharge(['email' => 'test@example.com', 'amount' => 1000]);
        $this->assertArrayHasKey('authorization_url', $res);
        $this->assertEquals('ref_123', $res['reference']);
    }
}
