<?php

declare(strict_types=1);

namespace Synapse\Agent;

final class AgentContext
{
    public function __construct(
        public readonly string $input,
        public array $messages = [],
        public array $metadata = [],
    ) {}
}
