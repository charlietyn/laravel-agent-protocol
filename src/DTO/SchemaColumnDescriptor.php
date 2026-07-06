<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class SchemaColumnDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<string, mixed>  $references
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $name,
        public string $type = 'mixed',
        public ?string $nativeType = null,
        public ?bool $nullable = null,
        public mixed $default = null,
        public bool $autoIncrement = false,
        public bool $primary = false,
        public bool $unique = false,
        public bool $indexed = false,
        public bool $sensitive = false,
        public ?string $label = null,
        public ?string $description = null,
        public string $descriptionSource = 'inferred',
        public array $references = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withOverrides(array $overrides): self
    {
        return new self(
            name: $this->name,
            type: $this->stringOrDefault($overrides['type'] ?? null, $this->type),
            nativeType: $this->stringOrNull($overrides['native_type'] ?? null) ?? $this->nativeType,
            nullable: array_key_exists('nullable', $overrides) ? $this->boolOrNull($overrides['nullable']) : $this->nullable,
            default: array_key_exists('default', $overrides) ? $overrides['default'] : $this->default,
            autoIncrement: array_key_exists('auto_increment', $overrides) ? (bool) $overrides['auto_increment'] : $this->autoIncrement,
            primary: array_key_exists('primary', $overrides) ? (bool) $overrides['primary'] : $this->primary,
            unique: array_key_exists('unique', $overrides) ? (bool) $overrides['unique'] : $this->unique,
            indexed: array_key_exists('indexed', $overrides) ? (bool) $overrides['indexed'] : $this->indexed,
            sensitive: array_key_exists('sensitive', $overrides) ? (bool) $overrides['sensitive'] : $this->sensitive,
            label: $this->stringOrNull($overrides['label'] ?? null) ?? $this->label,
            description: $this->stringOrNull($overrides['description'] ?? null) ?? $this->description,
            descriptionSource: isset($overrides['description']) ? 'config' : $this->descriptionSource,
            references: array_replace_recursive($this->references, $this->arrayValue($overrides['references'] ?? [])),
            meta: array_replace_recursive($this->meta, $this->arrayValue($overrides['meta'] ?? [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'name' => $this->name,
            'type' => $this->type,
            'native_type' => $this->nativeType,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'auto_increment' => $this->autoIncrement,
            'primary' => $this->primary,
            'unique' => $this->unique,
            'indexed' => $this->indexed,
            'sensitive' => $this->sensitive,
            'label' => $this->label,
            'description' => $this->description,
            'description_source' => $this->descriptionSource,
            'references' => $this->references,
            'meta' => $this->meta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function boolOrNull(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
