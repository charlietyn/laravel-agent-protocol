<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use JsonSerializable;

final readonly class BusinessScope implements JsonSerializable
{
    /**
     * @param array<int, BusinessScopeFilter> $filters
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $payloadConstraints
     * @param array<int, string|int> $allowedIds
     * @param array<int, string|int> $deniedIds
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $resource,
        public string $operation,
        public array $filters = [],
        public array $routeParams = [],
        public array $payloadConstraints = [],
        public array $allowedIds = [],
        public array $deniedIds = [],
        public string $mode = 'allow',
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    /**
     * @param array<int, BusinessScopeFilter|array<string, mixed>> $filters
     * @param array<string, mixed> $metadata
     */
    public static function enforce(string $resource, string $operation, array $filters = [], ?string $reason = null, array $metadata = []): self
    {
        return new self($resource, $operation, self::normalizeFilters($filters), mode: 'enforce', reason: $reason, metadata: $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function allow(string $resource, string $operation, ?string $reason = null, array $metadata = []): self
    {
        return new self($resource, $operation, mode: 'allow', reason: $reason, metadata: $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function deny(string $resource, string $operation, ?string $reason = null, array $metadata = []): self
    {
        return new self($resource, $operation, mode: 'deny', reason: $reason, metadata: $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function review(string $resource, string $operation, ?string $reason = null, array $metadata = []): self
    {
        return new self($resource, $operation, mode: 'review', reason: $reason, metadata: $metadata);
    }

    public function isDeny(): bool
    {
        return $this->mode === 'deny';
    }

    public function requiresReview(): bool
    {
        return $this->mode === 'review';
    }

    public function shouldEnforce(): bool
    {
        return $this->mode === 'enforce' && $this->filters !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'resource' => $this->resource,
            'operation' => $this->operation,
            'filters' => $this->filters,
            'route_params' => $this->routeParams,
            'payload_constraints' => $this->payloadConstraints,
            'allowed_ids' => $this->allowedIds,
            'denied_ids' => $this->deniedIds,
            'mode' => $this->mode,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param array<int, BusinessScopeFilter|array<string, mixed>> $filters
     * @return array<int, BusinessScopeFilter>
     */
    private static function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $filter) {
            if ($filter instanceof BusinessScopeFilter) {
                $normalized[] = $filter;
                continue;
            }

            if (is_array($filter)) {
                $candidate = BusinessScopeFilter::fromArray($filter);
                if ($candidate->field !== '') {
                    $normalized[] = $candidate;
                }
            }
        }

        return $normalized;
    }
}
