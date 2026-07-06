<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Passes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerPass;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\Metadata\AgentMetadataGraphBuilder;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;
use Ronu\LaravelAgentProtocol\Metadata\Readiness\ReadinessScorer;

final readonly class SemanticMetadataPass implements MetadataCompilerPass
{
    public function __construct(
        private ?ReadinessScorer $readinessScorer = null,
    ) {}

    public function compile(MetadataBuildContext $context, AgentMetadataGraphBuilder $builder): void
    {
        $referenceTables = $this->referenceTables($context);

        foreach ($builder->resources() as $resource) {
            $fieldDefinitions = $this->fieldDefinitions($context, $resource->key);
            $fields = array_map(
                fn (FieldDescriptor $field): FieldDescriptor => $this->enrichField($field, $fieldDefinitions[$field->name] ?? [], $referenceTables),
                $resource->fields,
            );

            $updated = $resource->withFields($fields);
            $builder->addResource($updated->withReadiness($this->scorer()->score($updated)));
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, array<string, mixed>>  $referenceTables
     */
    private function enrichField(FieldDescriptor $field, array $definition, array $referenceTables): FieldDescriptor
    {
        $metadata = $definition;

        if (($reference = $this->referenceForField($field->name, $definition, $referenceTables)) !== null) {
            $metadata['type'] ??= 'foreign_key';
            $metadata['reference'] = array_replace_recursive($reference, $this->arrayValue($definition['reference'] ?? []));
        }

        if ($metadata === []) {
            return $field;
        }

        return $field->withMetadata($metadata);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fieldDefinitions(MetadataBuildContext $context, string $resourceKey): array
    {
        $resources = $context->config('agent-protocol.resources', []);
        if (! is_array($resources)) {
            return [];
        }

        $resource = $resources[$resourceKey] ?? null;
        if (! is_array($resource) || ! isset($resource['fields']) || ! is_array($resource['fields'])) {
            return [];
        }

        $fields = [];
        foreach ($resource['fields'] as $name => $definition) {
            if (is_string($name) && is_array($definition)) {
                $fields[$name] = $definition;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function referenceTables(MetadataBuildContext $context): array
    {
        $configured = $context->config('agent-protocol.reference_tables', []);
        if (! is_array($configured)) {
            return [];
        }

        $tables = [];
        foreach ($configured as $key => $definition) {
            if (! is_string($key) || ! is_array($definition) || ! (bool) ($definition['enabled'] ?? true)) {
                continue;
            }

            $maxRecords = $this->positiveInt($definition['max_records'] ?? null, 100);
            $fields = $this->stringList($definition['fields'] ?? ['id', 'name']);
            $values = $this->inlineValues($definition, $fields, $maxRecords);
            $complete = count($values) <= $maxRecords && ($values !== [] || isset($definition['values']));

            if (count($values) > $maxRecords) {
                $values = [];
                $complete = false;
            }

            $lookupField = $this->stringOrDefault($definition['lookup_field'] ?? null, $fields[1] ?? $fields[0] ?? 'id');
            $resource = $this->stringOrDefault($definition['resource'] ?? null, $key);
            $tables[$key] = [
                'key' => $key,
                'resource' => $resource,
                'lookup_field' => $lookupField,
                'display_fields' => $this->stringList($definition['display_fields'] ?? $fields),
                'inline_values' => $complete ? $values : [],
                'complete' => $complete,
                'max_records' => $maxRecords,
                'hint' => $complete ? null : "Query {$resource} first to resolve by {$lookupField}.",
                '_foreign_keys' => $this->foreignKeys($key, $resource, $definition),
            ];
        }

        return $tables;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function inlineValues(array $definition, array $fields, int $maxRecords): array
    {
        if (isset($definition['values']) && is_array($definition['values'])) {
            return $this->normalizeRecords($definition['values'], $fields);
        }

        $modelClass = $this->stringOrNull($definition['model'] ?? null);
        if (! $modelClass || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        try {
            /** @var Model $model */
            $model = new $modelClass;
            $query = $model->newQuery()->limit($maxRecords + 1);

            if ($fields !== []) {
                $query->select($fields);
            }

            $values = [];
            foreach ($query->get() as $record) {
                if (is_object($record) && method_exists($record, 'getAttributes')) {
                    $values[] = $this->onlyFields($record->getAttributes(), $fields);
                }
            }

            return $values;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $records
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRecords(array $records, array $fields): array
    {
        $normalized = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $normalized[] = $this->onlyFields($record, $fields);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function onlyFields(array $record, array $fields): array
    {
        if ($fields === []) {
            return $record;
        }

        return array_intersect_key($record, array_flip($fields));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function foreignKeys(string $key, string $resource, array $definition): array
    {
        $configured = $this->stringList($definition['foreign_keys'] ?? []);
        if ($configured !== []) {
            return $configured;
        }

        $lastResourceSegment = Str::afterLast($resource, '.');

        return array_values(array_unique([
            Str::singular($key).'_id',
            $key.'_id',
            Str::singular($lastResourceSegment).'_id',
            $lastResourceSegment.'_id',
        ]));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, array<string, mixed>>  $referenceTables
     * @return array<string, mixed>|null
     */
    private function referenceForField(string $field, array $definition, array $referenceTables): ?array
    {
        $referenceTable = $this->stringOrNull($definition['reference_table'] ?? null);
        if ($referenceTable && isset($referenceTables[$referenceTable])) {
            return $this->publicReference($referenceTables[$referenceTable]);
        }

        foreach ($referenceTables as $reference) {
            if (in_array($field, $this->stringList($reference['_foreign_keys'] ?? []), true)) {
                return $this->publicReference($reference);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed>
     */
    private function publicReference(array $reference): array
    {
        unset($reference['_foreign_keys']);

        return $reference;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_string(...)));
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function positiveInt(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function scorer(): ReadinessScorer
    {
        return $this->readinessScorer ?? new ReadinessScorer;
    }
}
