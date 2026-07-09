<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;

/**
 * Contract test for {@see ResourceDescriptor}.
 *
 * ResourceDescriptor is the most-referenced DTO in the codebase after
 * AgentMetadataGraph (38 edges). It carries not just serialization but the
 * merge() logic every compiler pass relies on to combine resource metadata
 * from multiple sources. This test locks the envelope shape AND the merge /
 * with* / lookup behaviour so a change breaks the build, not the compiler.
 */
final class ResourceDescriptorContractTest extends TestCase
{
    private function makeResource(): ResourceDescriptor
    {
        return new ResourceDescriptor(
            key: 'security.user',
            module: 'security',
            name: 'user',
            fields: [new FieldDescriptor('email', 'string', fillable: true)],
            operations: [
                new OperationDescriptor(
                    scenario: 'query',
                    method: 'GET',
                    endpoint: '/api/security/users',
                    description: 'Query users.',
                    capabilities: new CapabilityDescriptor(query: true),
                    risk: 'low',
                ),
            ],
            filters: ['email'],
        );
    }

    public function test_envelope_exposes_exactly_the_documented_keys(): void
    {
        $payload = $this->makeResource()->toArray();

        self::assertSame([
            'key',
            'module',
            'name',
            'endpoint',
            'model',
            'table',
            'primary_key',
            'description',
            'fields',
            'relations',
            'operations',
            'capabilities',
            'filters',
            'security',
            'readiness',
            'visibility',
            'examples',
            'meta',
        ], array_keys($payload));
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $resource = $this->makeResource();

        self::assertSame($resource->toArray(), $resource->jsonSerialize());

        $roundTrip = json_decode(json_encode($resource, JSON_THROW_ON_ERROR), true);
        self::assertSame($resource->toArray(), $roundTrip);
    }

    public function test_merge_dedupes_operations_by_scenario_and_unions_filters(): void
    {
        $base = $this->makeResource();

        $other = new ResourceDescriptor(
            key: 'security.user',
            module: '',   // empty -> keeps base module
            name: '',     // empty -> keeps base name
            endpoint: '/api/security/users',
            fields: [new FieldDescriptor('email', 'string', fillable: false)], // same name -> dedup
            operations: [
                new OperationDescriptor(
                    scenario: 'create',
                    method: 'POST',
                    endpoint: '/api/security/users',
                    description: 'Create users.',
                    capabilities: new CapabilityDescriptor(create: true),
                    risk: 'medium',
                ),
            ],
            filters: ['email', 'name'],
        );

        $merged = $base->merge($other);

        // Empty module/name on the other side fall back to base.
        self::assertSame('security', $merged->module);
        self::assertSame('user', $merged->name);
        // Non-null endpoint from the other side is adopted.
        self::assertSame('/api/security/users', $merged->endpoint);
        // Operations union across distinct scenarios.
        self::assertSame(['query', 'create'], array_map(
            fn (OperationDescriptor $op): string => $op->scenario,
            $merged->operations,
        ));
        // Fields deduped by name (email appears once).
        self::assertCount(1, $merged->fields);
        // Filters unioned without duplicates.
        self::assertSame(['email', 'name'], $merged->filters);
    }

    public function test_with_readiness_and_with_fields_are_immutable(): void
    {
        $original = $this->makeResource();

        $withReadiness = $original->withReadiness(['score' => 80, 'status' => 'ready']);
        self::assertSame([], $original->readiness);
        self::assertSame(['score' => 80, 'status' => 'ready'], $withReadiness->readiness);

        $newFields = [new FieldDescriptor('name', 'string', fillable: true)];
        $withFields = $original->withFields($newFields);
        self::assertCount(1, $original->fields);
        self::assertSame('email', $original->fields[0]->name);
        self::assertSame('name', $withFields->fields[0]->name);
    }

    public function test_operation_lookup_by_scenario(): void
    {
        $resource = $this->makeResource();

        self::assertNotNull($resource->operation('query'));
        self::assertSame('query', $resource->operation('query')?->scenario);
        self::assertNull($resource->operation('nonexistent'));
    }
}
