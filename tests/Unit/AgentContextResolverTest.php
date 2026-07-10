<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\Runtime\Context\AgentContextResolver;
use Ronu\LaravelAgentProtocol\Runtime\Permissions\NullPermissionResolver;

final class AgentContextResolverTest extends TestCase
{
    public function test_from_request_prefers_authenticated_user_tenant_over_untrusted_header(): void
    {
        $user = new class
        {
            public string $tenant_id = 'tenant-real';

            public function getKey(): int
            {
                return 123;
            }
        };

        $request = Request::create('/agent', 'GET', server: ['HTTP_X_TENANT_ID' => 'tenant-attacker']);
        $request->setUserResolver(static fn (): object => $user);

        $context = (new AgentContextResolver(new NullPermissionResolver, [
            'tenant_header' => 'X-Tenant-Id',
            'tenant_attribute' => 'tenant_id',
        ]))->fromRequest($request);

        self::assertSame('tenant-real', $context->tenantId);
    }

    public function test_from_request_ignores_untrusted_tenant_header_when_user_has_no_tenant(): void
    {
        $request = Request::create('/agent', 'GET', server: ['HTTP_X_TENANT_ID' => 'tenant-attacker']);

        $context = (new AgentContextResolver(new NullPermissionResolver, [
            'tenant_header' => 'X-Tenant-Id',
            'tenant_attribute' => 'tenant_id',
            'trust_tenant_header' => false,
        ]))->fromRequest($request);

        self::assertNull($context->tenantId);
    }

    public function test_from_request_can_use_tenant_header_only_when_explicitly_trusted(): void
    {
        $request = Request::create('/agent', 'GET', server: ['HTTP_X_TENANT_ID' => 'tenant-prevalidated']);

        $context = (new AgentContextResolver(new NullPermissionResolver, [
            'tenant_header' => 'X-Tenant-Id',
            'tenant_attribute' => 'tenant_id',
            'trust_tenant_header' => true,
        ]))->fromRequest($request);

        self::assertSame('tenant-prevalidated', $context->tenantId);
    }
}
