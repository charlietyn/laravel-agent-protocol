<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\Metadata\AgentMetadataGraphBuilder;
use Ronu\LaravelAgentProtocol\Metadata\Introspection\ModelInspector;
use Ronu\LaravelAgentProtocol\Metadata\Introspection\RelationInspector;
use Ronu\LaravelAgentProtocol\Metadata\Introspection\RuleNormalizer;
use Ronu\LaravelAgentProtocol\Metadata\Introspection\ValidationInspector;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;
use Ronu\LaravelAgentProtocol\Metadata\OperationFactory;
use Ronu\LaravelAgentProtocol\Metadata\Passes\ConfiguredResourcePass;
use Ronu\LaravelAgentProtocol\Metadata\Providers\ConfigResourceProvider;
use Ronu\LaravelAgentProtocol\Tests\Fixtures\FakeModel;
use Ronu\LaravelAgentProtocol\Tests\Fixtures\FakeRequest;

final class MetadataCompilerTest extends TestCase
{
    public function test_configured_resource_pass_compiles_rest_generic_metadata_shape(): void
    {
        $container = new Container;
        $config = new Repository([
            'agent-protocol' => [
                'protocol_version' => '1.0',
                'discovery' => [
                    'default_module' => '--site--',
                ],
                'resources' => [
                    'security.fake-user' => [
                        'module' => 'security',
                        'model' => FakeModel::class,
                        'request' => FakeRequest::class,
                        'endpoint' => '/api/security/fake-users',
                        'operations' => [
                            'delete' => [
                                'method' => 'DELETE',
                                'endpoint' => '/api/security/fake-users/{id}',
                            ],
                        ],
                    ],
                ],
                'security' => [
                    'redact_sensitive_fields' => true,
                    'expose_sensitive_fields' => false,
                    'sensitive_fields' => ['password'],
                    'hidden_fields' => [],
                    'public_fields' => [],
                    'tenant_header' => 'X-Tenant-Id',
                    'locale_header' => 'Accept-Language',
                ],
            ],
            'rest-generic-class' => [
                'filtering' => [
                    'max_depth' => 4,
                ],
            ],
        ]);
        $container->instance('config', $config);

        $pass = new ConfiguredResourcePass(
            providers: [new ConfigResourceProvider($config)],
            modelInspector: new ModelInspector,
            relationInspector: new RelationInspector,
            validationInspector: new ValidationInspector($container, new RuleNormalizer),
            operationFactory: new OperationFactory,
        );

        $builder = new AgentMetadataGraphBuilder('1.0');
        $pass->compile(new MetadataBuildContext($container), $builder);
        $graph = $builder->build();
        $resource = $graph->resource('security.fake-user');

        self::assertNotNull($resource);
        self::assertSame('security', $resource->module);
        self::assertSame('/api/security/fake-users', $resource->operations[0]->endpoint);
        self::assertTrue($resource->capabilities?->query);
        self::assertTrue($resource->capabilities?->create);
        self::assertTrue($resource->capabilities?->update);
        self::assertTrue($resource->capabilities?->hierarchy);
        self::assertSame('agent_ready', $resource->readiness['status']);
        self::assertNotContains('password', array_map(fn ($field): string => $field->name, $resource->fields));

        $delete = $resource->operation('delete');

        self::assertNotNull($delete);
        self::assertSame('high', $delete->risk);
        self::assertTrue($delete->requiresConfirmation);
    }
}
