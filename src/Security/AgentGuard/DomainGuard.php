<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

final class DomainGuard
{
    public function check(IntentPlan $plan, BusinessDomainPolicy $policy): ?PolicyViolation
    {
        if (! $policy->enabled) {
            return null;
        }

        $module = $plan->module();

        if ($policy->isClosedWorld() && ! $policy->allowsModule($module)) {
            return PolicyViolation::outOfDomain(
                "Module [{$module}] is outside the configured ADP business domain.",
                ['module' => $module, 'resource' => $plan->resource],
            );
        }

        if (! $policy->allowsResource($plan->resource)) {
            return PolicyViolation::outOfDomain(
                "Resource [{$plan->resource}] is blocked or outside the configured ADP business domain.",
                ['resource' => $plan->resource],
            );
        }

        if (! $policy->allowsOperation($plan->operation)) {
            return PolicyViolation::outOfDomain(
                "Operation [{$plan->operation}] is outside the configured ADP business domain.",
                ['operation' => $plan->operation],
            );
        }

        $blockedTopic = $policy->blockedTopicIn($plan->naturalLanguageIntent);
        if ($blockedTopic !== null) {
            return PolicyViolation::outOfDomain(
                'The requested intent references a blocked business topic.',
                ['blocked_topic' => $blockedTopic],
            );
        }

        return null;
    }
}
