<?php

declare(strict_types=1);

namespace PHPAI\Agent\Middleware;

use PHPAI\Agent\AgentContext;
use PHPAI\Agent\AgentResponse;
use PHPAI\Observability\CostCalculator;

final class CostTracker implements MiddlewareInterface
{
    private float $totalCost = 0.0;
    private CostCalculator $calculator;

    public function __construct()
    {
        $this->calculator = new CostCalculator();
    }

    public function handle(AgentContext $context, callable $next): AgentResponse
    {
        $response = $next($context);

        if ($response->totalUsage) {
            $model = $context->metadata['model'] ?? 'gpt-4o';
            $this->totalCost += $this->calculator->calculate(
                $model,
                $response->totalUsage->promptTokens,
                $response->totalUsage->completionTokens,
            );
        }

        return $response;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }
}
