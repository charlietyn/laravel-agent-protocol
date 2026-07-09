<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

/**
 * Contract test for the ADP envelope produced by {@see AgentMetadataGraph}.
 *
 * AgentMetadataGraph is the single compiled graph every exporter, the HTTP
 * controller and the cache read from — the highest-betweenness node in the
 * codebase. This test locks the SHAPE of its serialized envelope so an
 * accidental change to the top-level keys, capabilities list, links map or
 * implementation block breaks the build instead of silently breaking every
 * downstream consumer.
 */
final class AgentMetadataGraphContractTest extends TestCase
{
    private function makeGraph(): AgentMetadataGraph
    {
        return new AgentMetadataGraph(
            protocolVersion: '1.0',
            generatedAt: new DateTimeImmutable('2026-07-05T00:00:00+00:00'),
            modules: [],
            resources: [],
        );
    }

    public function test_envelope_exposes_exactly_the_documented_top_level_keys(): void
    {
        $payload = $this->makeGraph()->toArray();

        self::assertSame([
            'protocol',
            'protocol_version',
            'generated_at',
            'implementation',
            'capabilities',
            'links',
            'modules',
            'resources',
            'documentation',
            'filter',
            'dictionary',
        ], array_keys($payload));
    }

    public function test_protocol_identity_is_stable(): void
    {
        $payload = $this->makeGraph()->toArray();

        self::assertSame('adp', $payload['protocol']);
        self::assertSame('1.0', $payload['protocol_version']);
        self::assertSame('2026-07-05T00:00:00+00:00', $payload['generated_at']);
    }

    public function test_implementation_block_is_stable(): void
    {
        $payload = $this->makeGraph()->toArray();

        self::assertSame([
            'package' => 'ronu/laravel-agent-protocol',
            'framework' => 'laravel',
            'integration' => 'ronu/rest-generic-class',
        ], $payload['implementation']);
    }

    public function test_capabilities_list_is_locked(): void
    {
        $payload = $this->makeGraph()->toArray();

        self::assertSame([
            'discovery',
            'query',
            'mutation_metadata',
            'validation',
            'dictionary',
            'filter_documentation',
            'risk_metadata',
            'readiness',
            'export',
        ], $payload['capabilities']);
    }

    public function test_links_map_is_locked(): void
    {
        $payload = $this->makeGraph()->toArray();

        self::assertSame([
            'self' => '/agent',
            'modules' => '/agent/modules',
            'resources' => '/agent/resources',
            'dictionary' => '/agent/dictionary',
            'filter_documentation' => '/agent/documentation/filter',
            'error_documentation' => '/agent/documentation/errors',
        ], $payload['links']);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $graph = $this->makeGraph();

        self::assertSame($graph->toArray(), $graph->jsonSerialize());

        $roundTrip = json_decode(json_encode($graph, JSON_THROW_ON_ERROR), true);
        self::assertSame($graph->toArray(), $roundTrip);
    }
}
