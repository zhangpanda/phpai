<?php

declare(strict_types=1);

namespace PHPAI\Observability;

final readonly class Span
{
    public function __construct(
        public string $id,
        public string $provider,
        public string $model,
        public float $duration,
        public int $promptTokens,
        public int $completionTokens,
        public float $cost,
        public \DateTimeImmutable $timestamp,
        public ?string $parentId = null,
    ) {}
}
