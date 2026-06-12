<?php

declare(strict_types=1);

namespace Synapse\RAG;

final readonly class Document
{
    public function __construct(
        public string $content,
        public array $metadata = [],
        public ?string $id = null,
    ) {}
}
