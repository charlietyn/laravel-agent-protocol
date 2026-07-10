<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\Runtime\Scope\BusinessScope;
use Ronu\LaravelAgentProtocol\Runtime\Scope\BusinessScopeEnforcer;
use Ronu\LaravelAgentProtocol\Runtime\Scope\BusinessScopeFilter;
use Ronu\LaravelAgentProtocol\Runtime\Scope\ConfigBusinessScopeResolver;
use Ronu\LaravelAgentProtocol\Runtime\Scope\ScopeConflictDetector;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;

final class BusinessScopeTest extends TestCase
{
    public function test_config_resolver_enforces_global_tenant_scope(): void
    {
        $resolver = new ConfigBusinessScopeResolver([
            'global_scopes' => [
                'tenant' => [
                    'enabled' => true,
                    'attribute' => 'tenant_id',
                    'field' => 'tenant_id',
                ],
            ],
        ]);

        $scope = $resolver->resolve(
            'example.resource',
            'query',
            new AgentContext(tenantId: 'tenant-7'),
        );

        self::assertSame('enforce', $scope->mode);
        self::assertSame('tenant_id', $scope->filters[0]->field);
        self::assertSame('tenant-7', $scope->filters[0]->value);
    }

    public function test_config_resolver_uses_resource_attribute_scope(): void
    {
        $resolver = new ConfigBusinessScopeResolver([
            'resources' => [
                'example.resource' => [
                    'filters' => [
                        ['field' => 'owner_id', 'attribute' => 'owner_id'],
                    ],
                ],
            ],
        ]);

        $scope = $resolver->resolve(
            'example.resource',
            'query',
            new AgentContext(attributes: ['owner_id' => '42']),
        );

        self::assertSame('enforce', $scope->mode);
        self::assertSame('owner_id', $scope->filters[0]->field);
        self::assertSame('42', $scope->filters[0]->value);
    }

    public function test_config_resolver_denies_when_required_scope_cannot_be_resolved(): void
    {
        $resolver = new ConfigBusinessScopeResolver([
            'fail_closed' => true,
            'resources' => [
                'example.resource' => [
                    'required' => true,
                    'filters' => [
                        ['field' => 'owner_id', 'attribute' => 'owner_id'],
                    ],
                ],
            ],
        ]);

        $scope = $resolver->resolve('example.resource', 'query', new AgentContext);

        self::assertSame('deny', $scope->mode);
        self::assertSame('ADP_SCOPE_MISSING_CONTEXT', $scope->metadata['code']);
    }

    public function test_config_resolver_denies_partially_resolved_required_scope(): void
    {
        $resolver = new ConfigBusinessScopeResolver([
            'fail_closed' => true,
            'resources' => [
                'example.resource' => [
                    'required' => true,
                    'filters' => [
                        ['field' => 'owner_id', 'attribute' => 'owner_id'],
                        ['field' => 'branch_id', 'attribute' => 'branch_id'],
                    ],
                ],
            ],
        ]);

        $scope = $resolver->resolve(
            'example.resource',
            'query',
            new AgentContext(attributes: ['owner_id' => '42']),
        );

        self::assertSame('deny', $scope->mode);
        self::assertSame('ADP_SCOPE_MISSING_CONTEXT', $scope->metadata['code']);
        self::assertSame('branch_id', $scope->metadata['missing_scope'][0]['field']);
    }

    public function test_enforcer_appends_scope_filters_with_and(): void
    {
        $plan = new IntentPlan(
            resource: 'example.resource',
            operation: 'query',
            filters: ['oper' => ['and' => ['status|=|active']]],
        );

        $scope = BusinessScope::enforce(
            resource: 'example.resource',
            operation: 'query',
            filters: [new BusinessScopeFilter('tenant_id', '=', 'tenant-7')],
        );

        $decision = (new BusinessScopeEnforcer)->apply($plan, $scope);

        self::assertTrue($decision->allowed);
        self::assertSame([
            'status|=|active',
            'tenant_id|=|tenant-7',
        ], $decision->plan?->filters['oper']['and']);
        self::assertTrue($decision->plan?->meta['business_scope']['applied']);
    }

    public function test_enforcer_preserves_structured_filters_when_appending_scope(): void
    {
        $structuredFilter = ['status' => ['active']];
        $plan = new IntentPlan(
            resource: 'example.resource',
            operation: 'query',
            filters: ['oper' => ['and' => [$structuredFilter]]],
        );

        $scope = BusinessScope::enforce(
            resource: 'example.resource',
            operation: 'query',
            filters: [new BusinessScopeFilter('tenant_id', '=', 'tenant-7')],
        );

        $decision = (new BusinessScopeEnforcer)->apply($plan, $scope);

        self::assertTrue($decision->allowed);
        self::assertSame($structuredFilter, $decision->plan?->filters['oper']['and'][0]);
        self::assertSame('tenant_id|=|tenant-7', $decision->plan?->filters['oper']['and'][1]);
    }

    public function test_conflict_detector_blocks_explicit_scope_contradiction(): void
    {
        $plan = new IntentPlan(
            resource: 'example.resource',
            operation: 'query',
            filters: ['oper' => ['and' => ['tenant_id|=|tenant-20']]],
        );

        $scope = BusinessScope::enforce(
            resource: 'example.resource',
            operation: 'query',
            filters: [new BusinessScopeFilter('tenant_id', '=', 'tenant-7')],
        );

        $decision = (new BusinessScopeEnforcer(new ScopeConflictDetector))->apply($plan, $scope);

        self::assertFalse($decision->allowed);
        self::assertSame('ADP_SCOPE_CONFLICT', $decision->code);
        self::assertSame('tenant_id', $decision->conflicts[0]['field']);
    }

    public function test_deny_scope_rejects_before_execution(): void
    {
        $plan = new IntentPlan(resource: 'example.resource', operation: 'query');
        $scope = BusinessScope::deny('example.resource', 'query', 'Missing business context.');

        $decision = (new BusinessScopeEnforcer)->apply($plan, $scope);

        self::assertFalse($decision->allowed);
        self::assertSame('ADP_SCOPE_DENIED', $decision->code);
    }
}
