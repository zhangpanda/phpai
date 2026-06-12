<?php

declare(strict_types=1);

namespace Synapse\Tools;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsTool
{
    public function __construct(
        public string $description,
        public ?string $name = null,
    ) {}
}
