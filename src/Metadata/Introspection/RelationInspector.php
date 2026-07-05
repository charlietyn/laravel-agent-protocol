<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Introspection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Ronu\LaravelAgentProtocol\DTO\RelationDescriptor;

final class RelationInspector
{
    /**
     * @return array<int, RelationDescriptor>
     */
    public function inspect(?string $modelClass): array
    {
        if (! $modelClass || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;
        $relationNames = $this->allowedRelations($modelClass);
        $relations = [];

        foreach ($relationNames as $name) {
            if (! method_exists($model, $name)) {
                $relations[] = new RelationDescriptor(name: $name, type: 'configured');

                continue;
            }

            try {
                $relation = $model->{$name}();
            } catch (\Throwable) {
                $relations[] = new RelationDescriptor(name: $name, type: 'configured');

                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            $relations[] = $this->describe($name, $relation);
        }

        return $relations;
    }

    /**
     * @return array<int, string>
     */
    private function allowedRelations(string $modelClass): array
    {
        if (! defined($modelClass.'::RELATIONS')) {
            return [];
        }

        $relations = constant($modelClass.'::RELATIONS');

        return is_array($relations) ? array_values($relations) : [];
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private function describe(string $name, Relation $relation): RelationDescriptor
    {
        $type = Str::of(class_basename($relation))->snake()->replace('_', '-')->toString();
        $related = $relation->getRelated();
        $foreignKey = method_exists($relation, 'getForeignKeyName') ? $relation->getForeignKeyName() : null;
        $ownerKey = $relation instanceof BelongsTo && method_exists($relation, 'getOwnerKeyName')
            ? $relation->getOwnerKeyName()
            : null;
        $localKey = $relation instanceof HasOneOrMany && method_exists($relation, 'getLocalKeyName')
            ? $relation->getLocalKeyName()
            : null;

        return new RelationDescriptor(
            name: $name,
            type: $type,
            relatedModel: $related::class,
            relatedResource: null,
            foreignKey: $foreignKey,
            ownerKey: $ownerKey,
            localKey: $localKey,
        );
    }
}
