<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use JsonSerializable;

final readonly class BusinessScopeFilter implements JsonSerializable
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $field,
        public string $operator = '=',
        public mixed $value = null,
        public string $source = 'business_scope',
        public array $metadata = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            field: self::stringOrDefault($data['field'] ?? null, ''),
            operator: self::stringOrDefault($data['operator'] ?? null, '='),
            value: $data['value'] ?? null,
            source: self::stringOrDefault($data['source'] ?? null, 'business_scope'),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    public function expression(): string
    {
        return $this->field.'|'.$this->operator.'|'.$this->stringValue($this->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
            'source' => $this->source,
            'metadata' => $this->metadata,
        ];
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private static function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }
}
