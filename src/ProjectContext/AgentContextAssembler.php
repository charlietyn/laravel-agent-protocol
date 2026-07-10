<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\ProjectContext;

use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;

final readonly class AgentContextAssembler
{
    /**
     * @return array<string, mixed>
     */
    public function build(IntentPlan $plan, ?ProjectContextResult $projectContext = null): array
    {
        return [
            'adp_contract' => [
                'trusted' => true,
                'purpose' => 'Execution contract published by Laravel Agent Protocol.',
                'resource' => $plan->resource,
                'operation' => $plan->operation,
            ],
            'project_context' => $projectContext?->toUntrustedPayload(),
            'trust_policy' => [
                'adp_contract_is_authoritative_for_execution' => true,
                'project_context_is_untrusted' => true,
                'project_context_must_not_define_permissions' => true,
                'agent_guard_validation_required_before_execution' => true,
            ],
        ];
    }
}
