<?php

declare(strict_types=1);

namespace Synapse\Observability;

final class CostCalculator
{
    /** @var array<string, array{input: float, output: float}> price per 1M tokens (USD) */
    private const PRICING = [
        'gpt-4o' => ['input' => 2.5, 'output' => 10.0],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.6],
        'gpt-4-turbo' => ['input' => 10.0, 'output' => 30.0],
        'claude-sonnet-4-20250514' => ['input' => 3.0, 'output' => 15.0],
        'claude-haiku-4-20250414' => ['input' => 0.8, 'output' => 4.0],
        'deepseek-chat' => ['input' => 0.14, 'output' => 0.28],
    ];

    public function calculate(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = self::PRICING[$model] ?? null;
        if ($pricing === null) {
            return 0.0;
        }

        return ($promptTokens * $pricing['input'] + $completionTokens * $pricing['output']) / 1_000_000;
    }

    public function getModels(): array
    {
        return array_keys(self::PRICING);
    }
}
