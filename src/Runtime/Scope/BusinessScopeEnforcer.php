<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;

final readonly class BusinessScopeEnforcer
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private ScopeConflictDetector $conflictDetector = new ScopeConflictDetector,
        private array $config = [],
    ) {}

    public function apply(IntentPlan $plan, BusinessScope $scope): BusinessScopeDecision
    {
        if ($scope->isDeny()) {
            return BusinessScopeDecision::rejected(
                scope: $scope,
                code: (string) ($scope->metadata['code'] ?? 'ADP_SCOPE_DENIED'),
                message: $scope->reason ?? 'The requested operation is denied by business scope.',
            );
        }

        if ($scope->requiresReview()) {
            return BusinessScopeDecision::rejected(
                scope: $scope,
                code: 'ADP_SCOPE_REVIEW_REQUIRED',
                message: $scope->reason ?? 'The requested operation requires business scope review.',
            );
        }

        $conflicts = $this->conflictDetector->conflicts($plan, $scope);
        if ($conflicts !== [] && $this->conflictPolicy() === 'deny') {
            return BusinessScopeDecision::rejected(
                scope: $scope,
                code: 'ADP_SCOPE_CONFLICT',
                message: 'The requested filters conflict with mandatory business scope.',
                conflicts: $conflicts,
            );
        }

        if (! $scope->shouldEnforce()) {
            return BusinessScopeDecision::allowed($plan, $scope, ['scope_applied' => false]);
        }

        $scopedPlan = $this->withScopeFilters($plan, $scope);

        return BusinessScopeDecision::allowed(
            plan: $scopedPlan,
            scope: $scope,
            metadata: [
                'scope_applied' => true,
                'scope_filters_hash' => $this->scopeHash($scope),
            ],
        );
    }

    private function withScopeFilters(IntentPlan $plan, BusinessScope $scope): IntentPlan
    {
        $filters = $plan->filters;
        $and = $filters['oper']['and'] ?? [];
        if (! is_array($and)) {
            $and = [$and];
        }

        $existing = array_values($and);
        $existingExpressions = array_values(array_filter($existing, static fn (mixed $filter): bool => is_string($filter)));

        foreach ($scope->filters as $filter) {
            $expression = $filter->expression();
            if (! in_array($expression, $existingExpressions, true)) {
                $existing[] = $expression;
                $existingExpressions[] = $expression;
            }
        }

        $filters['oper']['and'] = $existing;

        return new IntentPlan(
            resource: $plan->resource,
            operation: $plan->operation,
            select: $plan->select,
            filters: $filters,
            relations: $plan->relations,
            orderby: $plan->orderby,
            payload: $plan->payload,
            routeParams: [
                ...$plan->routeParams,
                ...$scope->routeParams,
            ],
            naturalLanguageIntent: $plan->naturalLanguageIntent,
            confirmed: $plan->confirmed,
            meta: [
                ...$plan->meta,
                'business_scope' => [
                    'mode' => $scope->mode,
                    'reason' => $scope->reason,
                    'filters_hash' => $this->scopeHash($scope),
                    'applied' => true,
                ],
            ],
        );
    }

    private function conflictPolicy(): string
    {
        $policy = $this->config['conflict_policy'] ?? 'deny';

        return is_string($policy) && in_array($policy, ['deny', 'append'], true) ? $policy : 'deny';
    }

    private function scopeHash(BusinessScope $scope): string
    {
        return hash('sha256', json_encode($scope->filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }
}
