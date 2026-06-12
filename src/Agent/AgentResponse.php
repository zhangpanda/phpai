<?php

declare(strict_types=1);

namespace Synapse\Agent;

use Synapse\Chat\Usage;

final readonly class AgentResponse
{
    public function __construct(
        public string $content,
        public array $steps = [],
        public ?Usage $totalUsage = null,
    ) {}
}
