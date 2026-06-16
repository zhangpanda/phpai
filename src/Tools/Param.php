<?php

declare(strict_types=1);

namespace PHPAI\Tools;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Param
{
    public function __construct(
        public string $description,
        public ?array $enum = null,
    ) {}
}
