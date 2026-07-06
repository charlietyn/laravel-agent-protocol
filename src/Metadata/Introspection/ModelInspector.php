<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Introspection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ValidationDescriptor;

final class ModelInspector
{
    /**
     * @param  array<string, ValidationDescriptor>  $validations
     * @param  array<string, mixed>  $visibility
     * @return array{table: ?string, primary_key: ?string, fields: array<int, FieldDescriptor>, meta: array<string, mixed>}
     */
    public function inspect(?string $modelClass, array $validations = [], array $visibility = []): array
    {
        if (! $modelClass || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return [
                'table' => null,
                'primary_key' => null,
                'fields' => [],
                'meta' => [],
            ];
        }

        /** @var Model $model */
        $model = new $modelClass;
        $fillable = $model->getFillable();
        $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];
        $hidden = method_exists($model, 'getHidden') ? $model->getHidden() : [];
        $publicFields = $this->stringList($visibility['public_fields'] ?? []);
        $hiddenFields = array_values(array_unique([...$hidden, ...$this->stringList($visibility['hidden_fields'] ?? [])]));
        $sensitiveFields = $this->stringList($visibility['sensitive_fields'] ?? []);
        $exposeSensitive = (bool) ($visibility['expose_sensitive_fields'] ?? false);
        $columns = $this->columnsFor($model);
        $validationByField = $this->validationByField($validations);
        $fieldNames = array_values(array_unique(array_filter([
            $model->getKeyName(),
            ...$columns,
            ...$fillable,
            ...array_keys($casts),
            ...array_keys($validationByField),
        ])));

        sort($fieldNames);
        $fieldNames = array_values(array_filter(
            $fieldNames,
            fn (string $field): bool => $this->shouldPublishField($field, $publicFields, $hiddenFields, $sensitiveFields, $exposeSensitive),
        ));

        $fields = array_map(
            function (string $field) use ($casts, $model, $fillable, $validationByField, $sensitiveFields, $hiddenFields): FieldDescriptor {
                $validationRules = $validationByField[$field] ?? [];
                $enumValues = $this->enumValuesFromRules($validationRules);
                $type = $this->typeFor($field, $casts, $model);

                return new FieldDescriptor(
                    name: $field,
                    type: $enumValues !== [] && in_array($type, ['mixed', 'string'], true) ? 'enum' : $type,
                    nullable: null,
                    fillable: in_array($field, $fillable, true),
                    cast: $this->castFor($field, $casts),
                    validationRules: $validationRules,
                    filterable: ! in_array($field, $sensitiveFields, true),
                    selectable: ! in_array($field, $hiddenFields, true),
                    sensitive: in_array($field, $sensitiveFields, true),
                    visible: true,
                    label: Str::of($field)->replace('_', ' ')->headline()->toString(),
                    enumValues: $enumValues,
                );
            },
            $fieldNames,
        );

        return [
            'table' => $model->getTable(),
            'primary_key' => $model->getKeyName(),
            'fields' => $fields,
            'meta' => [
                'connection' => $model->getConnectionName(),
                'key_type' => $model->getKeyType(),
                'incrementing' => $model->getIncrementing(),
                'timestamps' => $model->usesTimestamps(),
                'hidden_fields' => $hiddenFields,
                'sensitive_fields_redacted' => array_values(array_intersect($fieldNames, $sensitiveFields)) === []
                    && $sensitiveFields !== [],
                'soft_delete_column' => method_exists($model, 'getSoftDeleteColumn')
                    ? $model->getSoftDeleteColumn()
                    : null,
                'hierarchy_field' => defined($modelClass.'::HIERARCHY_FIELD_ID')
                    ? constant($modelClass.'::HIERARCHY_FIELD_ID')
                    : null,
                'rest_generic_model' => $this->isRestGenericModel($modelClass),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function columnsFor(Model $model): array
    {
        try {
            if (Schema::hasTable($model->getTable())) {
                return Schema::getColumnListing($model->getTable());
            }
        } catch (\Throwable) {
            // Schema is optional during documentation builds and package tests.
        }

        $modelClass = $model::class;
        if (defined($modelClass.'::columns')) {
            $columns = constant($modelClass.'::columns');

            return is_array($columns) ? array_values($columns) : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $casts
     */
    private function typeFor(string $field, array $casts, Model $model): string
    {
        if ($field === $model->getKeyName()) {
            return $model->getKeyType() === 'int' ? 'integer' : $model->getKeyType();
        }

        $cast = $this->castFor($field, $casts);

        return match ($cast) {
            'int', 'integer' => 'integer',
            'real', 'float', 'double', 'decimal' => 'number',
            'bool', 'boolean' => 'boolean',
            'array', 'json', 'collection', 'object' => 'object',
            'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp' => 'date-time',
            null => 'mixed',
            default => $cast,
        };
    }

    /**
     * @param  array<string, ValidationDescriptor>  $validations
     * @return array<string, array<int, string>>
     */
    private function validationByField(array $validations): array
    {
        $fields = [];

        foreach ($validations as $validation) {
            foreach ($validation->rules as $field => $rules) {
                $fields[$field] = array_values(array_unique([...(array) ($fields[$field] ?? []), ...$rules]));
            }
        }

        return $fields;
    }

    private function isRestGenericModel(string $modelClass): bool
    {
        $baseModel = 'Ronu\\RestGenericClass\\Core\\Models\\BaseModel';

        return class_exists($baseModel) && is_subclass_of($modelClass, $baseModel);
    }

    /**
     * @param  array<int, string>  $publicFields
     * @param  array<int, string>  $hiddenFields
     * @param  array<int, string>  $sensitiveFields
     */
    private function shouldPublishField(
        string $field,
        array $publicFields,
        array $hiddenFields,
        array $sensitiveFields,
        bool $exposeSensitive,
    ): bool {
        if ($publicFields !== []) {
            return in_array($field, $publicFields, true);
        }

        if (in_array($field, $hiddenFields, true)) {
            return false;
        }

        return $exposeSensitive || ! in_array($field, $sensitiveFields, true);
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

    /**
     * @param  array<string, mixed>  $casts
     */
    private function castFor(string $field, array $casts): ?string
    {
        $cast = $casts[$field] ?? null;

        if (is_string($cast)) {
            return $cast;
        }

        if (is_scalar($cast)) {
            return (string) $cast;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<int, array<string, mixed>>
     */
    private function enumValuesFromRules(array $rules): array
    {
        foreach ($rules as $rule) {
            if (! str_starts_with($rule, 'in:')) {
                continue;
            }

            return array_values(array_filter(array_map(
                fn (string $value): array => [
                    'value' => $value,
                    'label' => Str::of($value)->replace(['_', '-'], ' ')->headline()->toString(),
                ],
                array_filter(array_map('trim', explode(',', substr($rule, 3))), fn (string $value): bool => $value !== ''),
            )));
        }

        return [];
    }
}
