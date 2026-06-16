<?php

declare(strict_types=1);

namespace PHPAI\Chat;

/**
 * Retries failed API calls with exponential backoff.
 *
 * Retries on: network errors, 429 (rate limit), 5xx (server errors).
 * Does NOT retry on: 401/403 (auth), 400 (bad request).
 */
final class RetryHandler
{
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly int $baseDelayMs = 500,
        private readonly float $multiplier = 2.0,
        private readonly int $maxDelayMs = 30000,
    ) {}

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function execute(callable $fn): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $fn();
            } catch (ChatException $e) {
                $lastException = $e;

                if ($attempt >= $this->maxRetries || !$this->isRetryable($e)) {
                    throw $e;
                }

                $delay = $this->calculateDelay($attempt + 1, $e);
                usleep($delay * 1000);
            }
        }

        throw $lastException;
    }

    private function isRetryable(ChatException $e): bool
    {
        // Network/connection errors
        if ($e->getPrevious() instanceof \GuzzleHttp\Exception\ConnectException) {
            return true;
        }

        return match ($e->statusCode) {
            429 => true,         // Rate limited
            500, 502, 503 => true, // Server errors
            default => false,
        };
    }

    private function calculateDelay(int $attempt, ChatException $e): int
    {
        // Respect Retry-After header if available (429 responses)
        if ($e->statusCode === 429) {
            $prev = $e->getPrevious();
            if ($prev instanceof \GuzzleHttp\Exception\RequestException && $prev->hasResponse()) {
                $retryAfter = $prev->getResponse()->getHeaderLine('Retry-After');
                if ($retryAfter !== '' && is_numeric($retryAfter)) {
                    return min((int) ($retryAfter * 1000), $this->maxDelayMs);
                }
            }
        }

        // Exponential backoff with jitter
        $delay = (int) ($this->baseDelayMs * ($this->multiplier ** ($attempt - 1)));
        $jitter = random_int(0, (int) ($delay * 0.1));
        return min($delay + $jitter, $this->maxDelayMs);
    }
}
