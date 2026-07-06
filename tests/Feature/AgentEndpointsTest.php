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
