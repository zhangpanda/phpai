<?php

declare(strict_types=1);

namespace PHPAI\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use PHPAI\Chat\ChatException;
use PHPAI\Chat\RetryHandler;

final class RetryHandlerTest extends TestCase
{
    public function testSucceedsOnFirstAttempt(): void
    {
        $handler = new RetryHandler(maxRetries: 3, baseDelayMs: 1);
        $result = $handler->execute(fn() => 'ok');
        $this->assertSame('ok', $result);
    }

    public function testRetriesOnRateLimit(): void
    {
        $attempts = 0;
        $handler = new RetryHandler(maxRetries: 3, baseDelayMs: 1);

        $result = $handler->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new ChatException('rate limited', statusCode: 429, provider: 'test');
            }
            return 'recovered';
        });

        $this->assertSame(3, $attempts);
        $this->assertSame('recovered', $result);
    }

    public function testRetriesOnServerError(): void
    {
        $attempts = 0;
        $handler = new RetryHandler(maxRetries: 3, baseDelayMs: 1);

        $result = $handler->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 2) {
                throw new ChatException('server error', statusCode: 502, provider: 'test');
            }
            return 'ok';
        });

        $this->assertSame(2, $attempts);
    }

    public function testDoesNotRetryOnAuthError(): void
    {
        $handler = new RetryHandler(maxRetries: 3, baseDelayMs: 1);

        $this->expectException(ChatException::class);
        $handler->execute(function () {
            throw new ChatException('unauthorized', statusCode: 401, provider: 'test');
        });
    }

    public function testExhaustsRetries(): void
    {
        $handler = new RetryHandler(maxRetries: 2, baseDelayMs: 1);

        $this->expectException(ChatException::class);
        $this->expectExceptionMessage('always fails');
        $handler->execute(function () {
            throw new ChatException('always fails', statusCode: 500, provider: 'test');
        });
    }
}
