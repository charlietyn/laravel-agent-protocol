<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\RelationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;

final readonly class IntentPlanValidator
{
    private const DEFAULT_OPERATORS = [
        '=', '!=', '<', '>', '<=', '>=', 'like', 'not like', 'ilike', 'not ilike',
        'in', 'not in', 'between', 'not between', 'null', 'not null', 'exists',
        'not exists', 'date', 'not date',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    /**
     * @return array<int, PolicyViolation>
     */
    public function validate(IntentPlan $plan, AgentMetadataGraph $graph, AgentContext $context): array
    {
        $violations = [];
        $resource = $graph->resource($plan->resource);

        if (! $resource instanceof ResourceDescriptor) {
            return [PolicyViolation::unknownResource($plan->resource)];
        }

        $operation = $resource->operation($plan->operation);
        if ($operation === null) {
            return [PolicyViolation::unknownOperation($plan->operation, ['resource' => $resource->key])];
        }

        $violations = [
            ...$violations,
            ...$this->validateSelect($plan, $resource),
            ...$this->validateRelations($plan, $resource),
            ...$this->validateOrderBy($plan, $resource),
            ...$this->validatePayload($plan, $resource),
            ...$this->validateFilters($plan, $resource),
            ...$this->validatePermissions($operation->permissions, $context, $resource->key, $operation->scenario),
        ];

        return $violations;
    }

    /**
     * @return array<int, PolicyViolation>
     */
    private function validateSelect(IntentPlan $plan, ResourceDescriptor $resource): array
    {
        $violations = [];
        foreach ($plan->select as $field) {
            if ($field === '*') {
                continue;
            }

            $violation = $this->validateField($field, $resource, 'select');
            if ($violation instanceof PolicyViolation) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @return array<int, PolicyViolation>
     */
    private function validatePayload(IntentPlan $plan, ResourceDescriptor $resource): array
    {
        $violations = [];
        foreach (array_keys($plan->payload) as $field) {
            if (! is_string($field)) {
                continue;
            }

            $violation = $this->validateField($field, $resource, 'payload');
            if ($violation instanceof PolicyViolation) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @return array<int, PolicyViolation>
     */
    private function validateRelations(IntentPlan $plan, ResourceDescriptor $resource): array
    {
        $violations = [];
        $relations = $this->relationMap($resource);

        foreach ($plan->relations as $relationSpec) {
            if ($relationSpec === 'all') {
                continue;
            }

            [$relationName, $selectedFields] = $this->parseRelationSpec($relationSpec);
            $relation = $relations[$relationName] ?? null;

            if (! $relation instanceof RelationDescriptor || ! $relation->allowed) {
                $violations[] = PolicyViolation::invalidRelation($relationName, ['resource' => $resource->key]);
                continue;
            }

            if ($selectedFields !== [] && $relation->selectableFields !== []) {
                foreach ($selectedFields as $selectedField) {
                    if (! in_array($selectedField, $relation->selectableFields, true)) {
                        $violations[] = PolicyViolation::forbiddenField(
                            $relationName.'.'.$selectedField,
                            ['resource' => $resource->key, 'relation' => $relationName],
                        );
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @return array<int, PolicyViolation>
     */
    private function validateOrderBy(IntentPlan $plan, ResourceDescriptor $resource): array
    {
        $violations = [];
        foreach ($plan->orderby as $key => $value) {
            $field = is_string($key) ? $key : (is_string($value) ? $value : null);
            if ($field === null) {
                continue;
            }

            $violation = $this->validateField($field, $resource, 'orderby');
            if ($violation instanceof PolicyViolation) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @return array<int, PolicyViolation>
     */
    private function validateFilters(IntentPlan $plan, ResourceDescriptor $resource): array
    {
        $state = ['count' => 0];
        $violations = $this->walkFilterNode($plan->filters, $resource, 0, $state);
        $maxConditions = $this->intConfig('max_conditions');

        if ($maxConditions !== null && $state['count'] > $maxConditions) {
            $violations[] = new PolicyViolation(
                'ADP_TOO_MANY_CONDITIONS',
                'The requested filter exceeds the published max_conditions limit.',
                400,
                'blocked',
                ['count' => $state['count'], 'max_conditions' => $maxConditions],
            );
        }

        return $violations;
    }

    /**
     * @param array<string, int> $state
     * @return array<int, PolicyViolation>
     */
    private function walkFilterNode(mixed $node, ResourceDescriptor $resource, int $depth, array &$state): array
    {
        $violations = [];
        $maxDepth = $this->intConfig('max_depth');

        if ($maxDepth !== null && $depth > $maxDepth) {
            return [new PolicyViolation(
                'ADP_FILTER_TOO_DEEP',
                'The requested filter depth exceeds the published max_depth limit.',
                400,
                'blocked',
                ['depth' => $depth, 'max_depth' => $maxDepth],
            )];
        }

        if (is_string($node)) {
            $state['count']++;
            $violation = $this->validateFilterExpression($node, $resource);

            return $violation instanceof PolicyViolation ? [$violation] : [];
        }

        if (! is_array($node)) {
            return [];
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && ! in_array($key, ['oper', 'and', 'or', 'not'], true) && ! is_array($value)) {
                $state['count']++;
                $violation = $this->validateField($key, $resource, 'filter');
                if ($violation instanceof PolicyViolation) {
                    $violations[] = $violation;
                }
            }

            $violations = [
                ...$violations,
                ...$this->walkFilterNode($value, $resource, $depth + 1, $state),
            ];
        }

        return $violations;
    }

    private function validateFilterExpression(string $expression, ResourceDescriptor $resource): ?PolicyViolation
    {
        $parts = explode('|', $expression, 3);
        if (count($parts) < 2) {
            return PolicyViolation::invalidPlan(
                'Filter expressions must use the field|operator|value shape.',
                ['expression' => $expression],
            );
        }

        [$field, $operator] = $parts;
        $fieldViolation = $this->validateField($field, $resource, 'filter');
        if ($fieldViolation instanceof PolicyViolation) {
            return $fieldViolation;
        }

        $descriptor = $this->fieldMap($resource)[$field] ?? null;
        $allowedOperators = $descriptor instanceof FieldDescriptor && $descriptor->operators !== []
            ? $descriptor->operators
            : $this->allowedOperators();

        if (! in_array($operator, $allowedOperators, true)) {
            return PolicyViolation::invalidOperator($operator, [
                'field' => $field,
                'allowed_operators' => $allowedOperators,
            ]);
        }

        return null;
    }

    private function validateField(string $field, ResourceDescriptor $resource, string $usage): ?PolicyViolation
    {
        $field = trim($field);
        if ($field === '') {
            return PolicyViolation::forbiddenField($field, ['resource' => $resource->key, 'usage' => $usage]);
        }

        if (str_contains($field, '.')) {
            [$relationName, $relatedField] = explode('.', $field, 2);
            $relation = $this->relationMap($resource)[$relationName] ?? null;
            if (! $relation instanceof RelationDescriptor || ! $relation->allowed) {
                return PolicyViolation::invalidRelation($relationName, ['resource' => $resource->key, 'usage' => $usage]);
            }

            if ($relation->selectableFields !== [] && ! in_array($relatedField, $relation->selectableFields, true)) {
                return PolicyViolation::forbiddenField($field, ['resource' => $resource->key, 'usage' => $usage]);
            }

            return null;
        }

        $fields = $this->fieldMap($resource);
        $descriptor = $fields[$field] ?? null;

        if (! $descriptor instanceof FieldDescriptor) {
            return PolicyViolation::forbiddenField($field, ['resource' => $resource->key, 'usage' => $usage]);
        }

        if (! $descriptor->visible || $descriptor->sensitive) {
            return PolicyViolation::forbiddenField($field, ['resource' => $resource->key, 'usage' => $usage]);
        }

        if (in_array($usage, ['select', 'orderby'], true) && ! $descriptor->selectable) {
            return PolicyViolation::forbiddenField($field, ['resource' => $resource->key, 'usage' => $usage]);
        }

        if ($usage === 'filter' && ! $descriptor->filterable) {
            return PolicyViolation::forbiddenField($field, ['resource' => $resource->key, 'usage' => $usage]);
        }

        return null;
    }

    /**
     * @param array<int, string> $requiredPermissions
     * @return array<int, PolicyViolation>
     */
    private function validatePermissions(array $requiredPermissions, AgentContext $context, string $resource, string $operation): array
    {
        if ($requiredPermissions === [] || $context->permissions === []) {
            return [];
        }

        $missing = array_values(array_diff($requiredPermissions, $context->permissions));
        if ($missing === []) {
            return [];
        }

        return [new PolicyViolation(
            'ADP_FORBIDDEN_OPERATION',
            'The caller is not allowed to execute or inspect this ADP operation.',
            403,
            'blocked',
            ['resource' => $resource, 'operation' => $operation, 'missing_permissions' => $missing],
        )];
    }

    /**
     * @return array<string, FieldDescriptor>
     */
    private function fieldMap(ResourceDescriptor $resource): array
    {
        $map = [];
        foreach ($resource->fields as $field) {
            if ($field instanceof FieldDescriptor) {
                $map[$field->name] = $field;
            }
        }

        return $map;
    }

    /**
     * @return array<string, RelationDescriptor>
     */
    private function relationMap(ResourceDescriptor $resource): array
    {
        $map = [];
        foreach ($resource->relations as $relation) {
            if ($relation instanceof RelationDescriptor) {
                $map[$relation->name] = $relation;
            }
        }

        return $map;
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function parseRelationSpec(string $relationSpec): array
    {
        [$relation, $fields] = array_pad(explode(':', $relationSpec, 2), 2, '');

        return [
            trim($relation),
            $fields === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $fields)))),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedOperators(): array
    {
        $configured = $this->config['allowed_operators'] ?? null;

        return is_array($configured) && $configured !== []
            ? array_values(array_filter($configured, is_string(...)))
            : self::DEFAULT_OPERATORS;
    }

    private function intConfig(string $key): ?int
    {
        $value = $this->config[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
