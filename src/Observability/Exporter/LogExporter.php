<?php

declare(strict_types=1);

namespace Synapse\Observability\Exporter;

use Psr\Log\LoggerInterface;
use Synapse\Observability\Span;

final class LogExporter implements ExporterInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function export(Span $span): void
    {
        $this->logger->info('LLM call', [
            'provider' => $span->provider,
            'model' => $span->model,
            'duration' => round($span->duration, 3),
            'prompt_tokens' => $span->promptTokens,
            'completion_tokens' => $span->completionTokens,
            'cost_usd' => round($span->cost, 6),
        ]);
    }

    public function flush(): void {}
}
