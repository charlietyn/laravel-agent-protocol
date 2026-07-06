<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Cache;

use DateTimeImmutable;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;
use Ronu\LaravelAgentProtocol\DTO\DocumentationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\FilterDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ModuleDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\RelationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ValidationDescriptor;

final class CompiledMetadataGraphHydrator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function hydrate(array $payload): AgentMetadataGraph
    {
        return new AgentMetadataGraph(
            protocolVersion: $this->string($payload['protocol_version'] ?? null, '1.0'),
            generatedAt: new DateTimeImmutable($this->string($payload['generated_at'] ?? null, 'now')),
            modules: array_map(fn (array $module): ModuleDescriptor => $this->module($module), $this->arrayList($payload['modules'] ?? [])),
            resources: array_map(fn (array $resource): ResourceDescriptor => $this->resource($resource), $this->arrayList($payload['resources'] ?? [])),
            filterDocumentation: is_array($payload['filter'] ?? null) ? $this->filter($payload['filter']) : null,
            dictionary: $this->arrayValue($payload['dictionary'] ?? []),
            documentation: array_map(fn (array $documentation): DocumentationDescriptor => $this->documentation($documentation), $this->arrayList($payload['documentation'] ?? [])),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function module(array $payload): ModuleDescriptor
    {
        return new ModuleDescriptor(
            key: $this->string($payload['key'] ?? null),
            name: $this->string($payload['name'] ?? null),
            description: $this->nullableString($payload['description'] ?? null),
            resources: $this->stringList($payload['resources'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resource(array $payload): ResourceDescriptor
    {
        return new ResourceDescriptor(
            key: $this->string($payload['key'] ?? null),
            module: $this->string($payload['module'] ?? null),
            name: $this->string($payload['name'] ?? null),
            endpoint: $this->nullableString($payload['endpoint'] ?? null),
            model: $this->nullableString($payload['model'] ?? null),
            table: $this->nullableString($payload['table'] ?? null),
            primaryKey: $this->nullableString($payload['primary_key'] ?? null),
            description: $this->nullableString($payload['description'] ?? null),
            fields: array_map(fn (array $field): FieldDescriptor => $this->field($field), $this->arrayList($payload['fields'] ?? [])),
            relations: array_map(fn (array $relation): RelationDescriptor => $this->relation($relation), $this->arrayList($payload['relations'] ?? [])),
            operations: array_map(fn (array $operation): OperationDescriptor => $this->operation($operation), $this->arrayList($payload['operations'] ?? [])),
            capabilities: is_array($payload['capabilities'] ?? null) ? $this->capabilities($payload['capabilities']) : null,
            filters: $this->stringList($payload['filters'] ?? []),
            security: $this->arrayValue($payload['security'] ?? []),
            readiness: $this->arrayValue($payload['readiness'] ?? []),
            visibility: $this->arrayValue($payload['visibility'] ?? []),
            examples: $this->arrayList($payload['examples'] ?? []),
            meta: $this->arrayValue($payload['meta'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function field(array $payload): FieldDescriptor
    {
        return new FieldDescriptor(
            name: $this->string($payload['name'] ?? null),
            type: $this->string($payload['type'] ?? null, 'mixed'),
            nullable: array_key_exists('nullable', $payload) ? $this->nullableBool($payload['nullable']) : null,
            fillable: (bool) ($payload['fillable'] ?? false),
            cast: $this->nullableString($payload['cast'] ?? null),
            validationRules: $this->stringList($payload['validation_rules'] ?? []),
            filterable: (bool) ($payload['filterable'] ?? true),
            selectable: (bool) ($payload['selectable'] ?? true),
            sensitive: (bool) ($payload['sensitive'] ?? false),
            visible: (bool) ($payload['visible'] ?? true),
            operators: $this->stringList($payload['operators'] ?? []),
            label: $this->nullableString($payload['label'] ?? null),
            description: $this->nullableString($payload['description'] ?? null),
            enumValues: $this->arrayList($payload['enum_values'] ?? []),
            reference: $this->arrayValue($payload['reference'] ?? []),
            examples: $this->arrayList($payload['examples'] ?? []),
            meta: $this->arrayValue($payload['meta'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function relation(array $payload): RelationDescriptor
    {
        return new RelationDescriptor(
            name: $this->string($payload['name'] ?? null),
            type: $this->string($payload['type'] ?? null),
            relatedModel: $this->nullableString($payload['related_model'] ?? null),
            relatedResource: $this->nullableString($payload['related_resource'] ?? null),
            foreignKey: $this->nullableString($payload['foreign_key'] ?? null),
            ownerKey: $this->nullableString($payload['owner_key'] ?? null),
            localKey: $this->nullableString($payload['local_key'] ?? null),
            allowed: (bool) ($payload['allowed'] ?? true),
            maxDepth: is_numeric($payload['max_depth'] ?? null) ? (int) $payload['max_depth'] : null,
            selectableFields: $this->stringList($payload['selectable_fields'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function operation(array $payload): OperationDescriptor
    {
        return new OperationDescriptor(
            scenario: $this->string($payload['scenario'] ?? null),
            method: $this->string($payload['method'] ?? null, 'GET'),
            endpoint: $this->string($payload['endpoint'] ?? null),
            description: $this->string($payload['description'] ?? null),
            validation: is_array($payload['validation'] ?? null) ? $this->validation($payload['validation']) : null,
            capabilities: is_array($payload['capabilities'] ?? null) ? $this->capabilities($payload['capabilities']) : null,
            request: $this->arrayValue($payload['request'] ?? []),
            response: $this->arrayValue($payload['response'] ?? []),
            source: $this->string($payload['source'] ?? null, 'inferred'),
            risk: $this->string($payload['risk'] ?? null, 'medium'),
            requiresConfirmation: (bool) ($payload['requires_confirmation'] ?? false),
            permissions: $this->stringList($payload['permissions'] ?? []),
            security: $this->arrayValue($payload['security'] ?? []),
            examples: $this->arrayList($payload['examples'] ?? []),
            sideEffects: $this->arrayValue($payload['side_effects'] ?? []),
            annotations: $this->arrayValue($payload['annotations'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validation(array $payload): ValidationDescriptor
    {
        return new ValidationDescriptor(
            scenario: $this->string($payload['scenario'] ?? null),
            rules: $this->rules($payload['rules'] ?? []),
            messages: $this->stringMap($payload['messages'] ?? []),
            authorization: $this->arrayValue($payload['authorization'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function capabilities(array $payload): CapabilityDescriptor
    {
        return new CapabilityDescriptor(
            query: (bool) ($payload['query'] ?? false),
            create: (bool) ($payload['create'] ?? false),
            update: (bool) ($payload['update'] ?? false),
            bulkCreate: (bool) ($payload['bulk_create'] ?? false),
            bulkUpdate: (bool) ($payload['bulk_update'] ?? false),
            delete: (bool) ($payload['delete'] ?? false),
            restore: (bool) ($payload['restore'] ?? false),
            forceDelete: (bool) ($payload['force_delete'] ?? false),
            export: (bool) ($payload['export'] ?? false),
            aggregate: (bool) ($payload['aggregate'] ?? false),
            hierarchy: (bool) ($payload['hierarchy'] ?? false),
            softDeletes: (bool) ($payload['soft_deletes'] ?? false),
            permissioned: (bool) ($payload['permissioned'] ?? false),
            filters: $this->stringList($payload['filters'] ?? []),
            relations: $this->stringList($payload['relations'] ?? []),
            risks: $this->stringList($payload['risks'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function filter(array $payload): FilterDescriptor
    {
        return new FilterDescriptor(
            operators: $this->stringList($payload['operators'] ?? []),
            parameters: $this->arrayValue($payload['parameters'] ?? []),
            conditionFormat: $this->string($payload['condition_format'] ?? null),
            examples: $this->arrayList($payload['examples'] ?? []),
            limits: $this->arrayValue($payload['limits'] ?? []),
            strictRelations: (bool) ($payload['strict_relations'] ?? true),
            validateColumns: (bool) ($payload['validate_columns'] ?? true),
            strictColumnValidation: (bool) ($payload['strict_column_validation'] ?? true),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function documentation(array $payload): DocumentationDescriptor
    {
        return new DocumentationDescriptor(
            slug: $this->string($payload['slug'] ?? null),
            title: $this->string($payload['title'] ?? null),
            payload: $this->arrayValue($payload['payload'] ?? []),
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rules = [];
        foreach ($value as $field => $fieldRules) {
            if (is_string($field)) {
                $rules[$field] = $this->stringList($fieldRules);
            }
        }

        return $rules;
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
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_scalar($item)) {
                $map[$key] = (string) $item;
            }
        }

        return $map;
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableBool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }
}
