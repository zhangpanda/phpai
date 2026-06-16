<?php

declare(strict_types=1);

namespace PHPAI\MCP;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class McpTool
{
    public function __construct(
        public string $description,
        public ?string $name = null,
    ) {}
}
