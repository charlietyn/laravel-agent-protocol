<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\Security\OperationRiskClassifier;

final readonly class OperationRiskGuard
{
    /**
     * @param  array<int, string>  $confirmationRequiredFor
     */
    public function __construct(
        private array $confirmationRequiredFor = [OperationRiskClassifier::HIGH, OperationRiskClassifier::CRITICAL],
        private string $criticalDefault = 'block',
        private bool $blockWithoutConfirmation = true,
    ) {}

    public function check(OperationDescriptor $operation, IntentPlan $plan): ?PolicyViolation
    {
        if ($operation->risk === OperationRiskClassifier::CRITICAL && $this->criticalDefault === 'block') {
            return PolicyViolation::criticalBlocked(
                $operation->scenario,
                ['risk' => $operation->risk, 'resource' => $plan->resource],
            );
        }

        $requiresConfirmation = $operation->requiresConfirmation
            || in_array($operation->risk, $this->confirmationRequiredFor, true);

        if ($this->blockWithoutConfirmation && $requiresConfirmation && ! $plan->confirmed) {
            return PolicyViolation::confirmationRequired(
                $operation->scenario,
                $operation->risk,
                ['resource' => $plan->resource],
            );
        }

        return null;
    }
}
