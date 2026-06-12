<?php

declare(strict_types=1);

namespace Synapse\StructuredOutput;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsOutput
{
    public function __construct(
        public string $description = '',
    ) {}
}
