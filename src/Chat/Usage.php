<?php

declare(strict_types=1);

namespace PHPAI\Chat;

final readonly class Usage
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
    ) {}
}
