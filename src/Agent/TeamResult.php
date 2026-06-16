<?php

declare(strict_types=1);

namespace Synapse\Agent;

final readonly class TeamResult
{
    /** @param list<TeamStep> $steps */
    public function __construct(
        public string $content,
        public array $steps = [],
    ) {}
}
