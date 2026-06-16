<?php

declare(strict_types=1);

namespace PHPAI\Agent;

use PHPAI\Chat\Usage;

final readonly class AgentResponse
{
    public function __construct(
        public string $content,
        public array $steps = [],
        public ?Usage $totalUsage = null,
    ) {}
}
