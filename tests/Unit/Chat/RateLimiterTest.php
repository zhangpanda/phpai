<?php

declare(strict_types=1);

namespace Synapse\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use Synapse\Chat\RateLimiter;

final class RateLimiterTest extends TestCase
{
    public function testAcquiresTokensUpToMax(): void
    {
        $limiter = new RateLimiter(maxRequests: 3, perSeconds: 60);

        $this->assertTrue($limiter->tryAcquire());
        $this->assertTrue($limiter->tryAcquire());
        $this->assertTrue($limiter->tryAcquire());
        $this->assertFalse($limiter->tryAcquire()); // exhausted
    }

    public function testRemainingAccurate(): void
    {
        $limiter = new RateLimiter(maxRequests: 5, perSeconds: 60);
        $this->assertSame(5, $limiter->remaining());

        $limiter->tryAcquire();
        $this->assertSame(4, $limiter->remaining());
    }

    public function testRefillsOverTime(): void
    {
        $limiter = new RateLimiter(maxRequests: 10, perSeconds: 1); // 10/s

        // Drain all
        for ($i = 0; $i < 10; $i++) {
            $limiter->tryAcquire();
        }
        $this->assertFalse($limiter->tryAcquire());

        // Wait 200ms → should refill ~2 tokens
        usleep(200_000);
        $this->assertTrue($limiter->tryAcquire());
    }

    public function testWaitBlocksAndSucceeds(): void
    {
        $limiter = new RateLimiter(maxRequests: 10, perSeconds: 1); // 10/s

        // Drain all
        for ($i = 0; $i < 10; $i++) {
            $limiter->tryAcquire();
        }

        $start = microtime(true);
        $limiter->wait(); // should block briefly
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0.05, $elapsed); // waited at least 50ms
        $this->assertLessThan(0.5, $elapsed);     // but not too long
    }
}
