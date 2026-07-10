<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\ProjectContext;

use JsonSerializable;

/**
 * A technical question about the current project context.
 *
 * This DTO is intentionally not an executable tool plan. It is used only to
 * retrieve explanatory project context that may help an agent understand the
 * codebase before it produces an ADP IntentPlan.
 */
final readonly class ProjectContextQuery implements JsonSerializable
{
    /**
     * @param array<int, string> $keywords
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $question,
        public ?string $resource = null,
        public ?string $operation = null,
        public array $keywords = [],
        public int $maxNodes = 40,
        public int $maxEdges = 80,
        public int $maxChars = 12000,
        public array $metadata = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keywords = $data['keywords'] ?? [];

        return new self(
            question: (string) ($data['question'] ?? ''),
            resource: isset($data['resource']) && is_string($data['resource']) ? $data['resource'] : null,
            operation: isset($data['operation']) && is_string($data['operation']) ? $data['operation'] : null,
            keywords: is_array($keywords) ? array_values(array_filter($keywords, is_string(...))) : [],
            maxNodes: self::positiveInt($data['max_nodes'] ?? $data['maxNodes'] ?? 40, 40),
            maxEdges: self::positiveInt($data['max_edges'] ?? $data['maxEdges'] ?? 80, 80),
            maxChars: self::positiveInt($data['max_chars'] ?? $data['maxChars'] ?? 12000, 12000),
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public function withConfigLimits(array $config): self
    {
        return new self(
            question: $this->question,
            resource: $this->resource,
            operation: $this->operation,
            keywords: $this->keywords,
            maxNodes: self::positiveInt($config['max_nodes'] ?? $this->maxNodes, $this->maxNodes),
            maxEdges: self::positiveInt($config['max_edges'] ?? $this->maxEdges, $this->maxEdges),
            maxChars: self::positiveInt($config['max_chars'] ?? $this->maxChars, $this->maxChars),
            metadata: $this->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'question' => $this->question,
            'resource' => $this->resource,
            'operation' => $this->operation,
            'keywords' => $this->keywords,
            'max_nodes' => $this->maxNodes,
            'max_edges' => $this->maxEdges,
            'max_chars' => $this->maxChars,
            'metadata' => $this->metadata,
        ];
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : $default;
    }
}
