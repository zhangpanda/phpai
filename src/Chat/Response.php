<?php

declare(strict_types=1);

namespace PHPAI\Chat;

final readonly class Response
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        public string $content,
        public array $toolCalls,
        public Usage $usage,
        public string $model,
        public string $finishReason,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
