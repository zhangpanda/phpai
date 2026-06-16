<?php

declare(strict_types=1);

namespace PHPAI\Agent;

final readonly class TeamStep
{
    public function __construct(
        public string $agent,
        public string $input,
        public AgentResponse $response,
    ) {}
}
