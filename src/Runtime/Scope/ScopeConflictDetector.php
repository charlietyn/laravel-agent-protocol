<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;

final readonly class ScopeConflictDetector
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function conflicts(IntentPlan $plan, BusinessScope $scope): array
    {
        if ($scope->filters === []) {
            return [];
        }

        $requested = $this->extractRequestedFilters($plan->filters);
        $conflicts = [];

        foreach ($scope->filters as $required) {
            foreach ($requested as $candidate) {
                if (($candidate['field'] ?? null) !== $required->field) {
                    continue;
                }

                if ($this->isConflict($candidate, $required)) {
                    $conflicts[] = [
                        'field' => $required->field,
                        'required_operator' => $required->operator,
                        'required_value' => $required->value,
                        'requested_operator' => $candidate['operator'] ?? null,
                        'requested_value' => $candidate['value'] ?? null,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function isConflict(array $candidate, BusinessScopeFilter $required): bool
    {
        if ($required->operator !== '=') {
            return false;
        }

        $operator = strtolower((string) ($candidate['operator'] ?? '='));
        $requestedValue = (string) ($candidate['value'] ?? '');
        $requiredValue = (string) $required->value;

        return match ($operator) {
            '=' => $requestedValue !== $requiredValue,
            '!=' => $requestedValue === $requiredValue,
            'in' => ! in_array($requiredValue, $this->splitList($requestedValue), true),
            'not in' => in_array($requiredValue, $this->splitList($requestedValue), true),
            default => true,
        };
    }

    /**
     * @return array<int, array{field:string, operator:string, value:mixed}>
     */
    private function extractRequestedFilters(mixed $node): array
    {
        $filters = [];

        if (is_string($node)) {
            $parsed = $this->parseExpression($node);
            return $parsed === null ? [] : [$parsed];
        }

        if (! is_array($node)) {
            return [];
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && ! $this->isFilterControlKey($key)) {
                $filters[] = [
                    'field' => $key,
                    'operator' => is_array($value) ? 'in' : '=',
                    'value' => is_array($value) ? implode(',', array_map('strval', $value)) : $value,
                ];
                continue;
            }

            $filters = [
                ...$filters,
                ...$this->extractRequestedFilters($value),
            ];
        }

        return $filters;
    }

    /**
     * @return array{field:string, operator:string, value:mixed}|null
     */
    private function parseExpression(string $expression): ?array
    {
        $parts = explode('|', $expression, 3);
        if (count($parts) < 3) {
            return null;
        }

        return [
            'field' => trim($parts[0]),
            'operator' => trim($parts[1]),
            'value' => trim($parts[2]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }

    private function isFilterControlKey(string $key): bool
    {
        return in_array($key, ['oper', 'and', 'or', 'not'], true);
    }
}
