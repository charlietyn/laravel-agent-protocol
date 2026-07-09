<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\RelationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlanValidator;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\PolicyViolation;

/**
 * Direct unit tests for {@see IntentPlanValidator}.
 *
 * AgentGuardTest exercises the validator indirectly through the full guard for
 * the select / wildcard / field-visibility / legacy-filter / permission paths.
 * This suite covers the security-critical branches that flow does NOT reach
 * explicitly: relation validation, orderby, mutation payloads, the
 * field|operator|value filter grammar and the depth / condition limits.
 */
final class IntentPlanValidatorTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function validator(array $config = []): IntentPlanValidator
    {
        return new IntentPlanValidator($config);
    }

    private function graph(): AgentMetadataGraph
    {
        $resource = new ResourceDescriptor(
            key: 'security.user',
            module: 'security',
            name: 'user',
            fields: [
                new FieldDescriptor('email', operators: ['=', 'like']),
                new FieldDescriptor('name'),
                new FieldDescriptor('internal', selectable: false),
                new FieldDescriptor('password', sensitive: true),
            ],
            relations: [
                new RelationDescriptor(name: 'roles', type: 'belongsToMany', allowed: true, selectableFields: ['id', 'label']),
                new RelationDescriptor(name: 'secrets', type: 'hasMany', allowed: false),
            ],
            operations: [
                new OperationDescriptor(
                    scenario: 'query',
                    method: 'GET',
                    endpoint: '/api/security/users',
                    description: 'Query users.',
                ),
            ],
        );

        return new AgentMetadataGraph(
            protocolVersion: '1.0',
            generatedAt: new DateTimeImmutable('2026-07-05T00:00:00+00:00'),
            modules: [],
            resources: [$resource],
        );
    }

    /**
     * @param  array<int, PolicyViolation>  $violations
     * @return array<int, string>
     */
    private function codes(array $violations): array
    {
        return array_map(fn (PolicyViolation $v): string => $v->code, $violations);
    }

    public function test_unknown_resource_and_operation_short_circuit(): void
    {
        $graph = $this->graph();

        $unknownResource = $this->validator()->validate(
            new IntentPlan(resource: 'billing.invoice', operation: 'query'),
            $graph,
            new AgentContext,
        );
        self::assertSame(['ADP_RESOURCE_NOT_FOUND'], $this->codes($unknownResource));

        $unknownOperation = $this->validator()->validate(
            new IntentPlan(resource: 'security.user', operation: 'destroy'),
            $graph,
            new AgentContext,
        );
        self::assertSame(['ADP_OPERATION_NOT_FOUND'], $this->codes($unknownOperation));
    }

    public function test_disallowed_relation_is_blocked(): void
    {
        $plan = new IntentPlan(resource: 'security.user', operation: 'query', relations: ['secrets']);

        $violations = $this->validator()->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_INVALID_RELATION', $this->codes($violations));
    }

    public function test_relation_subfield_outside_selectable_set_is_blocked(): void
    {
        $plan = new IntentPlan(resource: 'security.user', operation: 'query', relations: ['roles:forbidden']);

        $violations = $this->validator()->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_FORBIDDEN_FIELD', $this->codes($violations));
    }

    public function test_orderby_on_non_selectable_field_is_blocked(): void
    {
        $plan = new IntentPlan(resource: 'security.user', operation: 'query', orderby: ['internal' => 'asc']);

        $violations = $this->validator()->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_FORBIDDEN_FIELD', $this->codes($violations));
    }

    public function test_payload_with_sensitive_field_is_blocked(): void
    {
        $plan = new IntentPlan(resource: 'security.user', operation: 'query', payload: ['password' => 'secret']);

        $violations = $this->validator()->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_FORBIDDEN_FIELD', $this->codes($violations));
    }

    public function test_filter_expression_with_disallowed_operator_is_blocked(): void
    {
        // 'email' publishes operators ['=', 'like']; '>' must be rejected.
        $plan = new IntentPlan(resource: 'security.user', operation: 'query', filters: ['email|>|x@example.com']);

        $violations = $this->validator()->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_INVALID_OPERATOR', $this->codes($violations));
    }

    public function test_malformed_filter_expression_is_blocked(): void
    {
        $plan = new IntentPlan(resource: 'security.user', operation: 'query', filters: ['emailonly']);

        $violations = $this->validator()->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_TOOL_PLAN_INVALID', $this->codes($violations));
    }

    public function test_max_conditions_limit_is_enforced(): void
    {
        $plan = new IntentPlan(
            resource: 'security.user',
            operation: 'query',
            filters: ['email|=|a@example.com', 'name|=|Ada'],
        );

        $violations = $this->validator(['max_conditions' => 1])->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_TOO_MANY_CONDITIONS', $this->codes($violations));
    }

    public function test_max_depth_limit_is_enforced(): void
    {
        $plan = new IntentPlan(
            resource: 'security.user',
            operation: 'query',
            filters: [[['email|=|a@example.com']]],
        );

        $violations = $this->validator(['max_depth' => 1])->validate($plan, $this->graph(), new AgentContext);

        self::assertContains('ADP_FILTER_TOO_DEEP', $this->codes($violations));
    }

    public function test_fully_valid_plan_produces_no_violations(): void
    {
        $plan = new IntentPlan(
            resource: 'security.user',
            operation: 'query',
            select: ['email', 'name'],
            filters: ['email|=|a@example.com'],
            relations: ['roles:id'],
            orderby: ['name' => 'asc'],
        );

        $violations = $this->validator()->validate($plan, $this->graph(), new AgentContext);

        self::assertSame([], $this->codes($violations));
    }
}
