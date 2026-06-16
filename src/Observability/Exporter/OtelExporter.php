<?php

declare(strict_types=1);

namespace PHPAI\Observability\Exporter;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use PHPAI\Observability\Span;

/**
 * Exports spans to OpenTelemetry Collector via OTLP HTTP/JSON.
 */
final class OtelExporter implements ExporterInterface
{
    private ClientInterface $client;
    /** @var list<array> */
    private array $buffer = [];

    public function __construct(
        private readonly string $endpoint = 'http://localhost:4318/v1/traces',
        private readonly string $serviceName = 'phpai',
        private readonly int $batchSize = 10,
        ?ClientInterface $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    public function __destruct()
    {
        $this->flush();
    }

    public function export(Span $span): void
    {
        $this->buffer[] = [
            'traceId' => bin2hex(random_bytes(16)),
            'spanId' => $span->id,
            'parentSpanId' => $span->parentId ?? '',
            'name' => "llm.{$span->provider}.chat",
            'kind' => 3,
            'startTimeUnixNano' => (int) ($span->timestamp->format('U.u') * 1_000_000_000),
            'endTimeUnixNano' => (int) (($span->timestamp->format('U.u') + $span->duration) * 1_000_000_000),
            'attributes' => [
                ['key' => 'llm.provider', 'value' => ['stringValue' => $span->provider]],
                ['key' => 'llm.model', 'value' => ['stringValue' => $span->model]],
                ['key' => 'llm.prompt_tokens', 'value' => ['intValue' => $span->promptTokens]],
                ['key' => 'llm.completion_tokens', 'value' => ['intValue' => $span->completionTokens]],
                ['key' => 'llm.cost_usd', 'value' => ['doubleValue' => $span->cost]],
            ],
        ];

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $payload = [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                    ],
                ],
                'scopeSpans' => [[
                    'scope' => ['name' => 'phpai', 'version' => '0.1.0'],
                    'spans' => $this->buffer,
                ]],
            ]],
        ];

        $this->buffer = [];

        try {
            $this->client->request('POST', $this->endpoint, [
                'json' => $payload,
                'timeout' => 5,
            ]);
        } catch (\Throwable) {
            // Silent fail — observability must not break the application
        }
    }
}
