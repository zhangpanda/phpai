<?php

declare(strict_types=1);

namespace PHPAI\StructuredOutput;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Param
{
    public function __construct(
        public string $description = '',
        public bool $required = true,
        public ?array $enum = null,
    ) {}
}
