<?php

declare(strict_types=1);

namespace Synapse\Agent\Middleware;

use Synapse\Agent\AgentContext;
use Synapse\Agent\AgentResponse;

interface MiddlewareInterface
{
    public function handle(AgentContext $context, callable $next): AgentResponse;
}
