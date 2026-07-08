<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

use JsonSerializable;

final readonly class AgentContext implements JsonSerializable
{
    /**
     * @param  array<int, string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public ?string $userIdentifier = null,
        public ?string $tenantId = null,
        public ?string $locale = null,
        public string $source = 'agent',
        public string $channel = 'unknown',
        public array $permissions = [],
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userIdentifier: self::stringOrNull($data['user_identifier'] ?? $data['user'] ?? null),
            tenantId: self::stringOrNull($data['tenant_id'] ?? $data['tenant'] ?? null),
            locale: self::stringOrNull($data['locale'] ?? null),
            source: self::stringOrDefault($data['source'] ?? null, 'agent'),
            channel: self::stringOrDefault($data['channel'] ?? null, 'unknown'),
            permissions: self::stringList($data['permissions'] ?? []),
            attributes: is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'user_identifier' => $this->userIdentifier,
            'tenant_id' => $this->tenantId,
            'locale' => $this->locale,
            'source' => $this->source,
            'channel' => $this->channel,
            'permissions' => $this->permissions,
            'attributes' => $this->attributes,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
