<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;

final readonly class ConfigBusinessScopeResolver implements BusinessScopeResolver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    public function resolve(string $resource, string $operation, AgentContext $context): BusinessScope
    {
        if (! (bool) ($this->config['enabled'] ?? true)) {
            return BusinessScope::allow($resource, $operation, 'Business scope is disabled.');
        }

        $filters = [];

        foreach ($this->globalFilters($context) as $filter) {
            $filters[] = $filter;
        }

        foreach ($this->resourceFilters($resource, $context) as $filter) {
            $filters[] = $filter;
        }

        if ($filters === []) {
            if ((bool) ($this->config['fail_closed'] ?? true) && $this->resourceRequiresScope($resource)) {
                return BusinessScope::deny(
                    resource: $resource,
                    operation: $operation,
                    reason: 'Business scope is required for this resource, but no scope filters could be resolved.',
                    metadata: ['code' => 'ADP_SCOPE_MISSING_CONTEXT'],
                );
            }

            return BusinessScope::allow($resource, $operation, 'No mandatory business scope configured for this resource.');
        }

        return BusinessScope::enforce(
            resource: $resource,
            operation: $operation,
            filters: $filters,
            reason: 'Business scope filters resolved from configuration and AgentContext.',
            metadata: ['resolver' => self::class],
        );
    }

    /**
     * @return array<int, BusinessScopeFilter>
     */
    private function globalFilters(AgentContext $context): array
    {
        $filters = [];
        $globalScopes = (array) ($this->config['global_scopes'] ?? []);

        foreach ($globalScopes as $scopeName => $scopeConfig) {
            if (! is_array($scopeConfig) || ! (bool) ($scopeConfig['enabled'] ?? true)) {
                continue;
            }

            $field = $this->stringOrNull($scopeConfig['field'] ?? null);
            $attribute = $this->stringOrNull($scopeConfig['attribute'] ?? null);
            if ($field === null || $attribute === null) {
                continue;
            }

            $value = $attribute === 'tenant_id' ? $context->tenantId : ($context->attributes[$attribute] ?? null);
            if ($value === null || $value === '') {
                continue;
            }

            $filters[] = new BusinessScopeFilter(
                field: $field,
                operator: $this->stringOrDefault($scopeConfig['operator'] ?? null, '='),
                value: $value,
                metadata: ['scope' => is_string($scopeName) ? $scopeName : 'global'],
            );
        }

        return $filters;
    }

    /**
     * @return array<int, BusinessScopeFilter>
     */
    private function resourceFilters(string $resource, AgentContext $context): array
    {
        $resourceConfig = $this->resourceScopeConfig($resource);
        if (! (bool) ($resourceConfig['enabled'] ?? true)) {
            return [];
        }

        $filters = [];
        foreach ((array) ($resourceConfig['filters'] ?? []) as $filterConfig) {
            if (! is_array($filterConfig)) {
                continue;
            }

            $field = $this->stringOrNull($filterConfig['field'] ?? null);
            if ($field === null) {
                continue;
            }

            $value = $filterConfig['value'] ?? null;
            $attribute = $this->stringOrNull($filterConfig['attribute'] ?? null);
            if ($attribute !== null) {
                $value = $attribute === 'tenant_id' ? $context->tenantId : ($context->attributes[$attribute] ?? null);
            }

            if (($value === null || $value === '') && (bool) ($filterConfig['required'] ?? true)) {
                continue;
            }

            $filters[] = new BusinessScopeFilter(
                field: $field,
                operator: $this->stringOrDefault($filterConfig['operator'] ?? null, '='),
                value: $value,
                metadata: ['scope' => 'resource', 'attribute' => $attribute],
            );
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceScopeConfig(string $resource): array
    {
        $resources = (array) ($this->config['resources'] ?? []);
        $resourceConfig = $resources[$resource] ?? [];

        return is_array($resourceConfig) ? $resourceConfig : [];
    }

    private function resourceRequiresScope(string $resource): bool
    {
        $resourceConfig = $this->resourceScopeConfig($resource);

        return (bool) ($resourceConfig['required'] ?? false);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return $this->stringOrNull($value) ?? $default;
    }
}
