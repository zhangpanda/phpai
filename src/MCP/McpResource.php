<?php

declare(strict_types=1);

namespace Synapse\MCP;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class McpResource
{
    public function __construct(
        public string $uri,
        public string $description,
        public ?string $mimeType = 'application/json',
    ) {}
}
