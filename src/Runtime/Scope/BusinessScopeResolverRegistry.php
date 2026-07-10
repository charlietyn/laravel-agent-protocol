<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use Illuminate\Contracts\Container\Container;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;

final readonly class BusinessScopeResolverRegistry
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private Container $container,
        private array $config = [],
    ) {}

    public function resolve(string $resource, string $operation, AgentContext $context): BusinessScope
    {
        return $this->resolverFor($resource)->resolve($resource, $operation, $context);
    }

    public function resolverFor(string $resource): BusinessScopeResolver
    {
        if (! (bool) ($this->config['enabled'] ?? true)) {
            return new NullBusinessScopeResolver;
        }

        $resolverClass = $this->resolverClassFor($resource);
        if ($resolverClass !== null && class_exists($resolverClass)) {
            $resolver = $this->container->make($resolverClass);
            if ($resolver instanceof BusinessScopeResolver) {
                return $resolver;
            }
        }

        return new ConfigBusinessScopeResolver($this->config);
    }

    private function resolverClassFor(string $resource): ?string
    {
        $resolvers = (array) ($this->config['resolvers'] ?? []);
        $resolver = $resolvers[$resource] ?? null;

        return is_string($resolver) && $resolver !== '' ? $resolver : null;
    }
}
