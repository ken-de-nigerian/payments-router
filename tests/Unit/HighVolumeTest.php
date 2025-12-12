<?php

namespace KenDeNigerian\PayZephyr\Tests\Unit;

class HighVolumeTest extends TestCase
{
    /** @test */
    public function it_handles_100_concurrent_payment_initializations()
    {
        $processes = [];

        for ($i = 0; $i < 100; $i++) {
            $processes[] = async(function() use ($i) {
                return Payment::amount(10000 + $i)
                    ->email("user{$i}@example.com")
                    ->callback('https://example.com/callback')
                    ->charge();
            });
        }

        $results = await($processes);

        // All should succeed
        $this->assertCount(100, $results);

        // All should have unique references
        $references = array_map(fn($r) => $r->reference, $results);
        $this->assertCount(100, array_unique($references));
    }

    /** @test */
    public function it_handles_1000_transactions_in_database()
    {
        PaymentTransaction::factory()->count(1000)->create();

        // Query should be performant
        $startTime = microtime(true);

        $successful = PaymentTransaction::successful()->count();
        $failed = PaymentTransaction::failed()->count();
        $pending = PaymentTransaction::pending()->count();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete in under 1 second
        $this->assertLessThan(1.0, $duration);

        $this->assertEquals(1000, $successful + $failed + $pending);
    }
}