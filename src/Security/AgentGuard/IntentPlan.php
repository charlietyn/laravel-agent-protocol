<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

use JsonSerializable;

final readonly class IntentPlan implements JsonSerializable
{
    /**
     * @param array<int, string> $select
     * @param array<string, mixed> $filters
     * @param array<int, string> $relations
     * @param array<int|string, mixed> $orderby
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $resource,
        public string $operation,
        public array $select = [],
        public array $filters = [],
        public array $relations = [],
        public array $orderby = [],
        public array $payload = [],
        public array $routeParams = [],
        public ?string $naturalLanguageIntent = null,
        public bool $confirmed = false,
        public array $meta = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            resource: self::stringOrDefault($data['resource'] ?? null, ''),
            operation: self::stringOrDefault($data['operation'] ?? $data['scenario'] ?? null, ''),
            select: self::stringList($data['select'] ?? []),
            filters: self::arrayValue($data['filters'] ?? $data['params']['filters'] ?? []),
            relations: self::stringList($data['relations'] ?? $data['params']['relations'] ?? []),
            orderby: is_array($data['orderby'] ?? null) ? $data['orderby'] : [],
            payload: self::arrayValue($data['payload'] ?? $data['body'] ?? []),
            routeParams: self::arrayValue($data['route_params'] ?? []),
            naturalLanguageIntent: self::stringOrNull($data['natural_language_intent'] ?? $data['intent'] ?? null),
            confirmed: (bool) ($data['confirmed'] ?? false),
            meta: self::arrayValue($data['meta'] ?? []),
        );
    }

    public function module(): string
    {
        $segments = explode('.', $this->resource, 2);

        return $segments[0] ?? $this->resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'resource' => $this->resource,
            'operation' => $this->operation,
            'select' => $this->select,
            'filters' => $this->filters,
            'relations' => $this->relations,
            'orderby' => $this->orderby,
            'payload' => $this->payload,
            'route_params' => $this->routeParams,
            'natural_language_intent' => $this->naturalLanguageIntent,
            'confirmed' => $this->confirmed,
            'meta' => $this->meta,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
