<?php

declare(strict_types=1);

namespace Synapse\Chat;

/**
 * Token bucket rate limiter for controlling API request frequency.
 *
 * Usage:
 *   $limiter = new RateLimiter(maxRequests: 60, perSeconds: 60); // 60 rpm
 *   $limiter->wait(); // blocks until a token is available
 *   $chat->send($messages);
 */
final class RateLimiter
{
    private float $tokens;
    private float $lastRefill;

    public function __construct(
        private readonly int $maxRequests,
        private readonly int $perSeconds = 60,
    ) {
        $this->tokens = (float) $maxRequests;
        $this->lastRefill = microtime(true);
    }

    /**
     * Block until a request token is available.
     */
    public function wait(): void
    {
        $this->refill();

        while ($this->tokens < 1.0) {
            $waitMs = (int) ceil((1.0 - $this->tokens) / $this->rate() * 1000);
            usleep(max($waitMs, 1) * 1000);
            $this->refill();
        }

        $this->tokens -= 1.0;
    }

    /**
     * Try to acquire a token without blocking.
     */
    public function tryAcquire(): bool
    {
        $this->refill();

        if ($this->tokens >= 1.0) {
            $this->tokens -= 1.0;
            return true;
        }

        return false;
    }

    /**
     * Remaining tokens available.
     */
    public function remaining(): int
    {
        $this->refill();
        return (int) floor($this->tokens);
    }

    private function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefill;
        $this->tokens = min((float) $this->maxRequests, $this->tokens + $elapsed * $this->rate());
        $this->lastRefill = $now;
    }

    private function rate(): float
    {
        return (float) $this->maxRequests / (float) $this->perSeconds;
    }
}
