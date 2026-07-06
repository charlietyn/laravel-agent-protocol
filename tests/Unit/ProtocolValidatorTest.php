<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\ModuleDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;
use Ronu\LaravelAgentProtocol\Validation\ProtocolValidator;

final class ProtocolValidatorTest extends TestCase
{
    public function test_validator_accepts_minimal_valid_graph(): void
    {
        $graph = new AgentMetadataGraph(
            protocolVersion: '1.0',
            generatedAt: new DateTimeImmutable,
            modules: [new ModuleDescriptor('security', 'security', resources: ['security.user'])],
            resources: [
                new ResourceDescriptor(
                    key: 'security.user',
                    module: 'security',
                    name: 'user',
                    operations: [
                        new OperationDescriptor(
                            scenario: 'query',
                            method: 'GET',
                            endpoint: '/api/security/users',
                            description: 'Query users.',
                        ),
                    ],
                ),
            ],
        );

        self::assertSame([], (new ProtocolValidator)->validate($graph));
    }

    public function test_validator_reports_broken_module_references(): void
    {
        $graph = new AgentMetadataGraph(
            protocolVersion: '1.0',
            generatedAt: new DateTimeImmutable,
            modules: [new ModuleDescriptor('security', 'security', resources: ['missing.user'])],
            resources: [],
        );

        self::assertNotSame([], (new ProtocolValidator)->validate($graph));
    }

    public function test_validator_reports_high_risk_operations_without_confirmation(): void
    {
        $graph = new AgentMetadataGraph(
            protocolVersion: '1.0',
            generatedAt: new DateTimeImmutable,
            modules: [new ModuleDescriptor('security', 'security', resources: ['security.user'])],
            resources: [
                new ResourceDescriptor(
                    key: 'security.user',
                    module: 'security',
                    name: 'user',
                    operations: [
                        new OperationDescriptor(
                            scenario: 'delete',
                            method: 'DELETE',
                            endpoint: '/api/security/users/{id}',
                            description: 'Delete users.',
                            risk: 'high',
                            requiresConfirmation: false,
                        ),
                    ],
                ),
            ],
        );

        self::assertNotSame([], (new ProtocolValidator)->validate($graph));
    }
}
