<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;

/**
 * Contract test for {@see OperationDescriptor}.
 *
 * OperationDescriptor (25 edges) is the per-scenario operation carried inside
 * every ResourceDescriptor and exported into the ADP envelope / MCP manifest.
 * This test locks its serialized key set, defaults and camelCase->snake_case
 * mapping so exporters and downstream agents keep receiving a stable shape.
 */
final class OperationDescriptorContractTest extends TestCase
{
    private function makeOperation(): OperationDescriptor
    {
        return new OperationDescriptor(
            scenario: 'query',
            method: 'GET',
            endpoint: '/api/security/users',
            description: 'Query users.',
            capabilities: new CapabilityDescriptor(query: true),
        );
    }

    public function test_envelope_exposes_exactly_the_documented_keys(): void
    {
        $payload = $this->makeOperation()->toArray();

        self::assertSame([
            'scenario',
            'method',
            'endpoint',
            'description',
            'validation',
            'capabilities',
            'request',
            'response',
            'source',
            'risk',
            'requires_confirmation',
            'permissions',
            'security',
            'examples',
            'side_effects',
            'annotations',
        ], array_keys($payload));
    }

    public function test_default_values_are_stable(): void
    {
        $payload = $this->makeOperation()->toArray();

        self::assertSame('query', $payload['scenario']);
        self::assertSame('GET', $payload['method']);
        self::assertNull($payload['validation']);
        self::assertSame('inferred', $payload['source']);
        self::assertSame('medium', $payload['risk']);
        self::assertFalse($payload['requires_confirmation']);
        self::assertSame([], $payload['permissions']);
        self::assertSame([], $payload['side_effects']);
        self::assertSame([], $payload['annotations']);
    }

    public function test_camel_case_properties_map_to_snake_case_keys(): void
    {
        $operation = new OperationDescriptor(
            scenario: 'delete',
            method: 'DELETE',
            endpoint: '/api/security/users/{id}',
            description: 'Delete a user.',
            source: 'config',
            risk: 'high',
            requiresConfirmation: true,
            sideEffects: ['cascade' => 'soft-delete'],
        );

        $payload = $operation->toArray();

        self::assertTrue($payload['requires_confirmation']);
        self::assertSame(['cascade' => 'soft-delete'], $payload['side_effects']);
        self::assertSame('config', $payload['source']);
        self::assertSame('high', $payload['risk']);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $operation = $this->makeOperation();

        self::assertSame($operation->toArray(), $operation->jsonSerialize());

        $roundTrip = json_decode(json_encode($operation, JSON_THROW_ON_ERROR), true);
        self::assertSame($operation->toArray(), $roundTrip);
    }
}
