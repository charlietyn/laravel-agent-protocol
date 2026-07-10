<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Context;

use Illuminate\Http\Request;
use Ronu\LaravelAgentProtocol\Runtime\Permissions\PermissionResolver;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;

final readonly class AgentContextResolver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private PermissionResolver $permissionResolver,
        private array $config = [],
    ) {}

    /**
     * @param array<string, mixed> $attributes
     */
    public function fromAuthenticatedUser(mixed $user, array $attributes = []): AgentContext
    {
        return new AgentContext(
            userIdentifier: $this->resolveUserIdentifier($user, $attributes),
            tenantId: $this->resolveTenantId($user, $attributes),
            locale: $this->stringOrNull($attributes['locale'] ?? null),
            source: $this->stringOrDefault($attributes['source'] ?? null, 'internal'),
            channel: $this->stringOrDefault($attributes['channel'] ?? null, 'service'),
            permissions: $this->permissionResolver->permissionsFor($user),
            attributes: $this->safeAttributes($user, $attributes),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fromRequest(Request $request, array $attributes = []): AgentContext
    {
        $user = $request->user();

        return new AgentContext(
            userIdentifier: $this->resolveUserIdentifier($user, $attributes),
            tenantId: $this->resolveTenantId($user, $attributes) ?? $this->trustedTenantHeader($request),
            locale: $this->stringOrNull($request->header((string) ($this->config['locale_header'] ?? 'Accept-Language'))),
            source: $this->stringOrDefault($attributes['source'] ?? null, 'http'),
            channel: $this->stringOrDefault($attributes['channel'] ?? null, 'api'),
            permissions: $this->permissionResolver->permissionsFor($user),
            attributes: $this->safeAttributes($user, $attributes),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): AgentContext
    {
        return AgentContext::fromArray($data);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function resolveUserIdentifier(mixed $user, array $attributes): ?string
    {
        if (array_key_exists('user_identifier', $attributes)) {
            return $this->stringOrNull($attributes['user_identifier']);
        }

        if (is_object($user) && method_exists($user, 'getKey')) {
            return $this->stringOrNull($user->getKey());
        }

        return $this->readObjectValue($user, (string) ($this->config['user_identifier_attribute'] ?? 'id'));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function resolveTenantId(mixed $user, array $attributes): ?string
    {
        if (array_key_exists('tenant_id', $attributes)) {
            return $this->stringOrNull($attributes['tenant_id']);
        }

        return $this->readObjectValue($user, (string) ($this->config['tenant_attribute'] ?? 'tenant_id'));
    }

    private function trustedTenantHeader(Request $request): ?string
    {
        if (! (bool) ($this->config['trust_tenant_header'] ?? false)) {
            return null;
        }

        return $this->stringOrNull($request->header((string) ($this->config['tenant_header'] ?? 'X-Tenant-Id')));
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function safeAttributes(mixed $user, array $attributes): array
    {
        $resolved = $attributes;
        unset($resolved['user_identifier'], $resolved['source'], $resolved['channel'], $resolved['locale']);

        foreach ((array) ($this->config['scope_attributes'] ?? []) as $attribute => $source) {
            if (! is_string($attribute) || ! is_string($source) || array_key_exists($attribute, $resolved)) {
                continue;
            }

            $value = $this->readObjectValue($user, $source);
            if ($value !== null) {
                $resolved[$attribute] = $value;
            }
        }

        return $resolved;
    }

    private function readObjectValue(mixed $source, string $key): ?string
    {
        if (! is_object($source) || $key === '') {
            return null;
        }

        if (isset($source->{$key})) {
            return $this->stringOrNull($source->{$key});
        }

        $method = 'get'.str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $key)));
        if (method_exists($source, $method)) {
            return $this->stringOrNull($source->{$method}());
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = is_scalar($value) ? trim((string) $value) : null;

        return $value !== null && $value !== '' ? $value : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return $this->stringOrNull($value) ?? $default;
    }
}
