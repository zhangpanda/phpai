<?php

declare(strict_types=1);

namespace Synapse\Agent\Middleware;

use Psr\Log\LoggerInterface;
use Synapse\Agent\AgentContext;
use Synapse\Agent\AgentResponse;

final class Logger implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function handle(AgentContext $context, callable $next): AgentResponse
    {
        $this->logger->info('Agent run started', ['input' => $context->input]);

        $start = microtime(true);
        $response = $next($context);
        $duration = microtime(true) - $start;

        $this->logger->info('Agent run completed', [
            'duration' => round($duration, 3),
            'steps' => count($response->steps),
            'tokens' => $response->totalUsage?->totalTokens,
        ]);

        return $response;
    }
}
