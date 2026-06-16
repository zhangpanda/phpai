<?php

declare(strict_types=1);

namespace PHPAI\RAG;

final readonly class Chunk
{
    public function __construct(
        public string $content,
        public array $embedding = [],
        public array $metadata = [],
        public ?string $id = null,
    ) {}
}
