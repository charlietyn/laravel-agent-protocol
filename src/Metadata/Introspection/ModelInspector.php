<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Introspection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ValidationDescriptor;

final class ModelInspector
{
    /**
     * @param  array<string, ValidationDescriptor>  $validations
     * @return array{table: ?string, primary_key: ?string, fields: array<int, FieldDescriptor>, meta: array<string, mixed>}
     */
    public function inspect(?string $modelClass, array $validations = []): array
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

        $fields = array_map(
            fn (string $field): FieldDescriptor => new FieldDescriptor(
                name: $field,
                type: $this->typeFor($field, $casts, $model),
                nullable: null,
                fillable: in_array($field, $fillable, true),
                cast: $this->castFor($field, $casts),
                validationRules: $validationByField[$field] ?? [],
            ),
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
}
