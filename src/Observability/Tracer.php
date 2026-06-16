<?php

declare(strict_types=1);

namespace PHPAI\Observability;

final class Tracer
{
    /** @var list<Span> */
    private array $spans = [];
    private CostCalculator $costCalculator;

    public function __construct(
        private readonly int $maxSpans = 10000,
    ) {
        $this->costCalculator = new CostCalculator();
    }

    public function record(
        string $provider,
        string $model,
        float $duration,
        int $promptTokens,
        int $completionTokens,
        ?string $parentId = null,
    ): Span {
        $span = new Span(
            id: bin2hex(random_bytes(8)),
            provider: $provider,
            model: $model,
            duration: $duration,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            cost: $this->costCalculator->calculate($model, $promptTokens, $completionTokens),
            timestamp: new \DateTimeImmutable(),
            parentId: $parentId,
        );

        $this->spans[] = $span;

        if (count($this->spans) > $this->maxSpans) {
            array_shift($this->spans);
        }

        return $span;
    }

    /** @return list<Span> */
    public function getSpans(): array
    {
        return $this->spans;
    }

    public function getTotalCost(): float
    {
        return array_sum(array_map(fn(Span $s) => $s->cost, $this->spans));
    }

    public function getTotalTokens(): int
    {
        return array_sum(array_map(fn(Span $s) => $s->promptTokens + $s->completionTokens, $this->spans));
    }

    public function reset(): void
    {
        $this->spans = [];
    }
}
