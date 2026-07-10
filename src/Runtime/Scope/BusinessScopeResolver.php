<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;

interface BusinessScopeResolver
{
    public function resolve(string $resource, string $operation, AgentContext $context): BusinessScope;
}
