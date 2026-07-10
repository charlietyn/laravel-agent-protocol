<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\ProjectContext\GraphifyLocalFileContextProvider;
use Ronu\LaravelAgentProtocol\ProjectContext\NullProjectContextProvider;
use Ronu\LaravelAgentProtocol\ProjectContext\ProjectContextManager;
use Ronu\LaravelAgentProtocol\ProjectContext\ProjectContextQuery;

final class ProjectContextBridgeTest extends TestCase
{
    public function test_null_provider_is_disabled_and_returns_untrusted_empty_context(): void
    {
        $provider = new NullProjectContextProvider;
        $result = $provider->query(new ProjectContextQuery('Explain authentication'));

        self::assertFalse($provider->enabled());
        self::assertTrue($result->empty());
        self::assertFalse($result->jsonSerialize()['trusted']);
        self::assertSame('untrusted_project_context', $result->toUntrustedPayload()['type']);
    }

    public function test_graphify_provider_reports_missing_graph_as_unavailable(): void
    {
        $provider = new GraphifyLocalFileContextProvider([
            'enabled' => true,
            'mode' => 'local_file',
            'path' => sys_get_temp_dir().'/missing-graphify-out-'.uniqid(),
            'graph_json' => 'graph.json',
        ]);

        $health = $provider->health();

        self::assertFalse($health->available);
        self::assertContains('Graphify graph.json was not found.', $health->warnings);
    }

    public function test_graphify_provider_queries_nodes_and_edges_from_local_graph(): void
    {
        $dir = $this->makeGraph([
            'nodes' => [
                ['id' => 'AuthController', 'type' => 'class', 'path' => 'app/Http/Controllers/AuthController.php', 'summary' => 'Handles authentication login flow.'],
                ['id' => 'InvoiceService', 'type' => 'class', 'path' => 'app/Services/InvoiceService.php', 'summary' => 'Handles invoices.'],
            ],
            'edges' => [
                ['source' => 'AuthController', 'target' => 'JWTAuth', 'type' => 'uses'],
                ['source' => 'InvoiceService', 'target' => 'Client', 'type' => 'uses'],
            ],
        ]);

        $provider = new GraphifyLocalFileContextProvider([
            'enabled' => true,
            'mode' => 'local_file',
            'path' => $dir,
            'graph_json' => 'graph.json',
            'max_nodes' => 10,
            'max_edges' => 10,
            'max_chars' => 5000,
        ]);

        $result = $provider->query(new ProjectContextQuery('Explain authentication AuthController JWTAuth'));

        self::assertFalse($result->trusted);
        self::assertNotEmpty($result->nodes);
        self::assertNotEmpty($result->edges);
        self::assertSame('untrusted_project_context', $result->toUntrustedPayload()['type']);
        self::assertStringContainsString('AuthController', json_encode($result->nodes, JSON_THROW_ON_ERROR));
    }

    public function test_graphify_provider_skips_sensitive_graph_items(): void
    {
        $dir = $this->makeGraph([
            'nodes' => [
                ['id' => 'SafeAuthController', 'summary' => 'Handles authentication login flow.'],
                ['id' => 'PasswordDump', 'summary' => 'Contains password and token values.'],
            ],
            'edges' => [],
        ]);

        $provider = new GraphifyLocalFileContextProvider([
            'enabled' => true,
            'mode' => 'local_file',
            'path' => $dir,
            'graph_json' => 'graph.json',
            'deny_sensitive_terms' => ['password', 'token'],
        ]);

        $result = $provider->query(new ProjectContextQuery('authentication password token'));
        $encoded = json_encode($result->nodes, JSON_THROW_ON_ERROR);

        self::assertStringContainsString('SafeAuthController', $encoded);
        self::assertStringNotContainsString('PasswordDump', $encoded);
        self::assertNotEmpty($result->warnings);
    }

    public function test_manager_builds_context_pack_with_clear_trust_rules(): void
    {
        $manager = new ProjectContextManager(new NullProjectContextProvider, ['enabled' => false]);

        $pack = $manager->contextPack(new ProjectContextQuery('Explain login'));

        self::assertSame('untrusted_project_context', $pack['project_context']['type']);
        self::assertContains('Only ADP metadata can define executable resources, operations, fields, filters and relations.', $pack['rules']);
    }

    /**
     * @param array<string, mixed> $graph
     */
    private function makeGraph(array $graph): string
    {
        $dir = sys_get_temp_dir().'/graphify-out-test-'.uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/graph.json', json_encode($graph, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $dir;
    }
}
