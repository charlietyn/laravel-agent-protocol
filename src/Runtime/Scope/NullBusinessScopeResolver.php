<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;

final readonly class NullBusinessScopeResolver implements BusinessScopeResolver
{
    public function resolve(string $resource, string $operation, AgentContext $context): BusinessScope
    {
        return BusinessScope::allow($resource, $operation, 'Business scope is disabled or no resolver is configured.');
    }
}
