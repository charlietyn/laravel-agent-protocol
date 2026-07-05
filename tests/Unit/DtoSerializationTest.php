<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ModuleDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;

final class DtoSerializationTest extends TestCase
{
    public function test_graph_serializes_to_adp_envelope(): void
    {
        $graph = new AgentMetadataGraph(
            protocolVersion: '1.0',
            generatedAt: new DateTimeImmutable('2026-07-05T00:00:00+00:00'),
            modules: [new ModuleDescriptor('security', 'security', resources: ['security.user'])],
            resources: [
                new ResourceDescriptor(
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
                        ),
                    ],
                ),
            ],
        );

        $payload = $graph->toArray();

        self::assertSame('adp', $payload['protocol']);
        self::assertSame('1.0', $payload['protocol_version']);
        self::assertSame('security.user', $payload['resources'][0]['key']);
        self::assertSame('email', $payload['resources'][0]['fields'][0]['name']);
    }
}
