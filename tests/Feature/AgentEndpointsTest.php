<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Feature;

use Ronu\LaravelAgentProtocol\Tests\TestCase;

final class AgentEndpointsTest extends TestCase
{
    public function test_agent_resource_endpoint_exposes_hardened_metadata(): void
    {
        $response = $this->getJson('/agent/resources/security.fake-user');

        $response->assertOk()
            ->assertJsonPath('key', 'security.fake-user')
            ->assertJsonPath('endpoint', '/api/security/fake-users')
            ->assertJsonPath('relations', [])
            ->assertJsonPath('readiness.status', 'agent_ready');

        $fields = array_column($response->json('fields'), 'name');

        self::assertContains('email', $fields);
        self::assertNotContains('password', $fields);
    }

    public function test_resource_endpoint_exposes_semantic_field_metadata_and_references(): void
    {
        $response = $this->getJson('/agent/resources/security.fake-user');

        $response->assertOk();

        $status = collect($response->json('fields'))->firstWhere('name', 'status');
        $parent = collect($response->json('fields'))->firstWhere('name', 'parent_id');

        self::assertSame('User status', $status['label']);
        self::assertSame('enum', $status['type']);
        self::assertSame('active', $status['enum_values'][0]['value']);
        self::assertSame('security.fake-user', $parent['reference']['resource']);
        self::assertTrue($parent['reference']['complete']);
        self::assertSame('Root User', $parent['reference']['inline_values'][0]['name']);
    }

    public function test_bundle_endpoint_supports_full_and_slim_modes(): void
    {
        $full = $this->getJson('/agent/bundle?mode=full');
        $slim = $this->getJson('/agent/bundle?mode=slim');

        $full->assertOk()
            ->assertJsonPath('bundle.mode', 'full')
            ->assertHeader('ETag');

        $slim->assertOk()
            ->assertJsonPath('bundle.mode', 'slim')
            ->assertHeader('Last-Modified');

        $fullParent = collect($full->json('resources.0.fields'))->firstWhere('name', 'parent_id');
        $slimParent = collect($slim->json('resources.0.fields'))->firstWhere('name', 'parent_id');

        self::assertArrayHasKey('inline_values', $fullParent['reference']);
        self::assertArrayNotHasKey('inline_values', $slimParent['reference']);
    }

    public function test_operation_endpoint_exposes_risk_and_confirmation(): void
    {
        $response = $this->getJson('/agent/resources/security.fake-user/operations/delete');

        $response->assertOk()
            ->assertJsonPath('scenario', 'delete')
            ->assertJsonPath('risk', 'high')
            ->assertJsonPath('requires_confirmation', true);
    }

    public function test_filter_documentation_exposes_limits(): void
    {
        $response = $this->getJson('/agent/documentation/filter');

        $response->assertOk()
            ->assertJsonPath('limits.max_depth', 3)
            ->assertJsonPath('limits.max_conditions', 25)
            ->assertJsonPath('strict_relations', true);
    }
}
