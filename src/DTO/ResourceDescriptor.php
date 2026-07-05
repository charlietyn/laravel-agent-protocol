<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class ResourceDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, FieldDescriptor>  $fields
     * @param  array<int, RelationDescriptor>  $relations
     * @param  array<int, OperationDescriptor>  $operations
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public string $module,
        public string $name,
        public ?string $model = null,
        public ?string $table = null,
        public ?string $primaryKey = null,
        public ?string $description = null,
        public array $fields = [],
        public array $relations = [],
        public array $operations = [],
        public ?CapabilityDescriptor $capabilities = null,
        public array $meta = [],
    ) {}

    public function merge(self $other): self
    {
        $operations = $this->mergeByKey($this->operations, $other->operations, 'scenario');
        $relations = $this->mergeByKey($this->relations, $other->relations, 'name');
        $fields = $this->mergeByKey($this->fields, $other->fields, 'name');

        return new self(
            key: $this->key,
            module: $other->module ?: $this->module,
            name: $other->name ?: $this->name,
            model: $other->model ?? $this->model,
            table: $other->table ?? $this->table,
            primaryKey: $other->primaryKey ?? $this->primaryKey,
            description: $other->description ?? $this->description,
            fields: $fields,
            relations: $relations,
            operations: $operations,
            capabilities: $this->capabilities && $other->capabilities
                ? $this->capabilities->merge($other->capabilities)
                : ($other->capabilities ?? $this->capabilities),
            meta: array_replace_recursive($this->meta, $other->meta),
        );
    }

    /**
     * @template T of object
     *
     * @param  array<int, T>  $first
     * @param  array<int, T>  $second
     * @return array<int, T>
     */
    private function mergeByKey(array $first, array $second, string $property): array
    {
        $merged = [];

        foreach ([...$first, ...$second] as $item) {
            if (! property_exists($item, $property)) {
                $merged[] = $item;

                continue;
            }

            $merged[$item->{$property}] = $item;
        }

        return array_values($merged);
    }

    public function operation(string $scenario): ?OperationDescriptor
    {
        foreach ($this->operations as $operation) {
            if ($operation->scenario === $scenario) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'key' => $this->key,
            'module' => $this->module,
            'name' => $this->name,
            'model' => $this->model,
            'table' => $this->table,
            'primary_key' => $this->primaryKey,
            'description' => $this->description,
            'fields' => $this->fields,
            'relations' => $this->relations,
            'operations' => $this->operations,
            'capabilities' => $this->capabilities,
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
}
