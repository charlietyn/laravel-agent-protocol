<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\ModuleDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ValidationDescriptor;
use Ronu\LaravelAgentProtocol\Exporters\JsonSchemaMetadataExporter;
use Ronu\LaravelAgentProtocol\Exporters\McpManifestExporter;

final class ExporterTest extends TestCase
{
    public function test_json_schema_and_mcp_exporters_are_derived_from_operations(): void
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
                            scenario: 'create',
                            method: 'POST',
                            endpoint: '/api/security/users',
                            description: 'Create users.',
                            validation: new ValidationDescriptor('create', ['email' => ['required', 'email']]),
                            risk: 'medium',
                        ),
                    ],
                ),
            ],
        );

        $schema = (new JsonSchemaMetadataExporter)->export($graph);
        $mcp = (new McpManifestExporter)->export($graph);

        self::assertStringContainsString('security.user.create', $schema);
        self::assertStringContainsString('create_security_user', $mcp);
    }
}
