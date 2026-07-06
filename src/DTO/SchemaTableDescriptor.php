<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class SchemaTableDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, SchemaColumnDescriptor>  $columns
     * @param  array<int, string>  $primaryKey
     * @param  array<int, array<string, mixed>>  $indexes
     * @param  array<int, array<string, mixed>>  $foreignKeys
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $name,
        public string $type = 'table',
        public ?string $schema = null,
        public ?string $module = null,
        public ?string $label = null,
        public ?string $description = null,
        public string $descriptionSource = 'inferred',
        public bool $expose = false,
        public bool $cacheable = false,
        public bool $referenceTable = false,
        public ?string $resource = null,
        public ?string $lookupField = null,
        public ?int $maxRecords = null,
        public ?int $rowEstimate = null,
        public array $columns = [],
        public array $primaryKey = [],
        public array $indexes = [],
        public array $foreignKeys = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array<int, SchemaColumnDescriptor>|null  $columns
     * @param  array<string, mixed>  $overrides
     */
    public function withOverrides(array $overrides, ?array $columns = null): self
    {
        return new self(
            name: $this->name,
            type: $this->stringOrDefault($overrides['type'] ?? null, $this->type),
            schema: $this->stringOrNull($overrides['schema'] ?? null) ?? $this->schema,
            module: $this->stringOrNull($overrides['module'] ?? null) ?? $this->module,
            label: $this->stringOrNull($overrides['label'] ?? null) ?? $this->label,
            description: $this->stringOrNull($overrides['description'] ?? null) ?? $this->description,
            descriptionSource: isset($overrides['description']) ? 'config' : $this->descriptionSource,
            expose: array_key_exists('expose', $overrides) ? (bool) $overrides['expose'] : $this->expose,
            cacheable: array_key_exists('cacheable', $overrides) ? (bool) $overrides['cacheable'] : $this->cacheable,
            referenceTable: array_key_exists('reference_table', $overrides) ? (bool) $overrides['reference_table'] : $this->referenceTable,
            resource: $this->stringOrNull($overrides['resource'] ?? null) ?? $this->resource,
            lookupField: $this->stringOrNull($overrides['lookup_field'] ?? null) ?? $this->lookupField,
            maxRecords: is_numeric($overrides['max_records'] ?? null) ? (int) $overrides['max_records'] : $this->maxRecords,
            rowEstimate: is_numeric($overrides['row_estimate'] ?? null) ? (int) $overrides['row_estimate'] : $this->rowEstimate,
            columns: $columns ?? $this->columns,
            primaryKey: $this->stringList($overrides['primary_key'] ?? $this->primaryKey),
            indexes: $this->arrayList($overrides['indexes'] ?? $this->indexes),
            foreignKeys: $this->arrayList($overrides['foreign_keys'] ?? $this->foreignKeys),
            meta: array_replace_recursive($this->meta, $this->arrayValue($overrides['meta'] ?? [])),
        );
    }

    public function withClassification(bool $cacheable, bool $referenceTable, ?string $lookupField, ?int $maxRecords): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            schema: $this->schema,
            module: $this->module,
            label: $this->label,
            description: $this->description,
            descriptionSource: $this->descriptionSource,
            expose: $this->expose,
            cacheable: $cacheable,
            referenceTable: $referenceTable,
            resource: $this->resource,
            lookupField: $lookupField,
            maxRecords: $maxRecords,
            rowEstimate: $this->rowEstimate,
            columns: $this->columns,
            primaryKey: $this->primaryKey,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            meta: $this->meta,
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
            'schema' => $this->schema,
            'module' => $this->module,
            'label' => $this->label,
            'description' => $this->description,
            'description_source' => $this->descriptionSource,
            'expose' => $this->expose,
            'cacheable' => $this->cacheable,
            'reference_table' => $this->referenceTable,
            'resource' => $this->resource,
            'lookup_field' => $this->lookupField,
            'max_records' => $this->maxRecords,
            'row_estimate' => $this->rowEstimate,
            'columns' => $this->columns,
            'primary_key' => $this->primaryKey,
            'indexes' => $this->indexes,
            'foreign_keys' => $this->foreignKeys,
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

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arrayList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
