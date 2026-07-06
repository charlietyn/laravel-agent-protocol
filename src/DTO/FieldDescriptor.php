<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class FieldDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, string>  $validationRules
     * @param  array<int, string>  $operators
     * @param  array<int, array<string, mixed>>  $enumValues
     * @param  array<string, mixed>  $reference
     * @param  array<int, array<string, mixed>>  $examples
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $name,
        public string $type = 'mixed',
        public ?bool $nullable = null,
        public bool $fillable = false,
        public ?string $cast = null,
        public array $validationRules = [],
        public bool $filterable = true,
        public bool $selectable = true,
        public bool $sensitive = false,
        public bool $visible = true,
        public array $operators = [],
        public ?string $label = null,
        public ?string $description = null,
        public array $enumValues = [],
        public array $reference = [],
        public array $examples = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            name: $this->name,
            type: $this->stringOrDefault($metadata['type'] ?? null, $this->type),
            nullable: array_key_exists('nullable', $metadata) ? $this->boolOrNull($metadata['nullable']) : $this->nullable,
            fillable: array_key_exists('fillable', $metadata) ? (bool) $metadata['fillable'] : $this->fillable,
            cast: $this->stringOrNull($metadata['cast'] ?? null) ?? $this->cast,
            validationRules: $this->stringList($metadata['validation_rules'] ?? $this->validationRules),
            filterable: array_key_exists('filterable', $metadata) ? (bool) $metadata['filterable'] : $this->filterable,
            selectable: array_key_exists('selectable', $metadata) ? (bool) $metadata['selectable'] : $this->selectable,
            sensitive: array_key_exists('sensitive', $metadata) ? (bool) $metadata['sensitive'] : $this->sensitive,
            visible: array_key_exists('visible', $metadata) ? (bool) $metadata['visible'] : $this->visible,
            operators: $this->stringList($metadata['operators'] ?? $this->operators),
            label: $this->stringOrNull($metadata['label'] ?? null) ?? $this->label,
            description: $this->stringOrNull($metadata['description'] ?? null) ?? $this->description,
            enumValues: $this->arrayList($metadata['enum_values'] ?? $metadata['values'] ?? $this->enumValues),
            reference: array_replace_recursive($this->reference, $this->arrayValue($metadata['reference'] ?? [])),
            examples: $this->arrayList($metadata['examples'] ?? $this->examples),
            meta: array_replace_recursive($this->meta, $this->arrayValue($metadata['meta'] ?? [])),
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
            'nullable' => $this->nullable,
            'fillable' => $this->fillable,
            'cast' => $this->cast,
            'validation_rules' => $this->validationRules,
            'filterable' => $this->filterable,
            'selectable' => $this->selectable,
            'sensitive' => $this->sensitive,
            'visible' => $this->visible,
            'operators' => $this->operators,
            'label' => $this->label,
            'description' => $this->description,
            'enum_values' => $this->enumValues,
            'reference' => $this->reference,
            'examples' => $this->examples,
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

        $items = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $items[] = $item;

                continue;
            }

            if (is_scalar($item)) {
                $items[] = [
                    'value' => is_string($key) ? $key : (string) $item,
                    'label' => (string) $item,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
