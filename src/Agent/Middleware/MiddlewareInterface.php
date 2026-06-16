<?php

declare(strict_types=1);

namespace PHPAI\Agent\Middleware;

use PHPAI\Agent\AgentContext;
use PHPAI\Agent\AgentResponse;

interface MiddlewareInterface
{
    public function handle(AgentContext $context, callable $next): AgentResponse;
}
