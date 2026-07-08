<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\Security\OperationRiskClassifier;

final readonly class ToolExecutionGuard
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $config = [],
        private ?DomainGuard $domainGuard = null,
        private ?PromptInjectionSignalDetector $promptInjectionDetector = null,
        private ?IntentPlanValidator $intentPlanValidator = null,
        private ?OperationRiskGuard $operationRiskGuard = null,
    ) {}

    public function authorize(IntentPlan $plan, AgentMetadataGraph $graph, ?AgentContext $context = null): IntentValidationResult
    {
        $context ??= new AgentContext;
        $config = $this->effectiveConfig();

        if (! (bool) ($config['enabled'] ?? true)) {
            return IntentValidationResult::allowed($plan->resource, $plan->operation, meta: ['guard_disabled' => true]);
        }

        if ((bool) ($config['prompt_injection']['enabled'] ?? true)) {
            $hits = $this->detector($config)->detectPlan($plan);
            if ($hits !== []) {
                return IntentValidationResult::rejected(
                    [PolicyViolation::untrustedInstruction(['signals' => $hits])],
                    $plan->resource,
                    $plan->operation,
                );
            }
        }

        $domainPolicy = BusinessDomainPolicy::fromConfig($config['domain'] ?? []);
        $domainViolation = ($this->domainGuard ?? new DomainGuard)->check($plan, $domainPolicy);
        if ($domainViolation instanceof PolicyViolation) {
            return IntentValidationResult::rejected([$domainViolation], $plan->resource, $plan->operation);
        }

        $validatorViolations = $this->validator($config)->validate($plan, $graph, $context);
        $resource = $graph->resource($plan->resource);
        $operation = $resource?->operation($plan->operation);

        if ($validatorViolations !== []) {
            return IntentValidationResult::rejected(
                $validatorViolations,
                $plan->resource,
                $plan->operation,
                $operation?->risk,
                (bool) ($operation?->requiresConfirmation ?? false),
            );
        }

        if ($operation !== null) {
            $riskViolation = $this->riskGuard($config)->check($operation, $plan);
            if ($riskViolation instanceof PolicyViolation) {
                return IntentValidationResult::rejected(
                    [$riskViolation],
                    $plan->resource,
                    $plan->operation,
                    $operation->risk,
                    $operation->requiresConfirmation,
                );
            }

            return IntentValidationResult::allowed(
                $plan->resource,
                $plan->operation,
                $operation->risk,
                $operation->requiresConfirmation,
            );
        }

        return IntentValidationResult::allowed($plan->resource, $plan->operation);
    }

    /**
     * @return array<string, mixed>
     */
    private function effectiveConfig(): array
    {
        return array_replace_recursive([
            'enabled' => true,
            'mode' => 'closed_world',
            'allowed_operators' => [
                '=', '!=', '<', '>', '<=', '>=', 'like', 'not like', 'ilike', 'not ilike',
                'in', 'not in', 'between', 'not between', 'null', 'not null', 'exists',
                'not exists', 'date', 'not date',
            ],
            'max_depth' => 5,
            'max_conditions' => 100,
            'domain' => [
                'enabled' => true,
                'mode' => 'closed',
                'allowed_modules' => [],
                'allowed_resources' => [],
                'blocked_resources' => [],
                'blocked_topics' => [],
                'allowed_operations' => [],
            ],
            'prompt_injection' => [
                'enabled' => true,
                'patterns' => [],
            ],
            'risk' => [
                'confirmation_required_for' => [OperationRiskClassifier::HIGH, OperationRiskClassifier::CRITICAL],
                'critical_default' => 'block',
                'block_without_confirmation' => true,
            ],
        ], $this->config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function detector(array $config): PromptInjectionSignalDetector
    {
        if ($this->promptInjectionDetector instanceof PromptInjectionSignalDetector) {
            return $this->promptInjectionDetector;
        }

        $patterns = $config['prompt_injection']['patterns'] ?? [];

        return new PromptInjectionSignalDetector(is_array($patterns) ? array_values(array_filter($patterns, is_string(...))) : []);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function validator(array $config): IntentPlanValidator
    {
        return $this->intentPlanValidator ?? new IntentPlanValidator([
            'allowed_operators' => $config['allowed_operators'] ?? [],
            'max_depth' => $config['max_depth'] ?? null,
            'max_conditions' => $config['max_conditions'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function riskGuard(array $config): OperationRiskGuard
    {
        if ($this->operationRiskGuard instanceof OperationRiskGuard) {
            return $this->operationRiskGuard;
        }

        $risk = $config['risk'] ?? [];
        $confirmationRequiredFor = $risk['confirmation_required_for'] ?? [OperationRiskClassifier::HIGH, OperationRiskClassifier::CRITICAL];

        return new OperationRiskGuard(
            confirmationRequiredFor: is_array($confirmationRequiredFor) ? array_values(array_filter($confirmationRequiredFor, is_string(...))) : [],
            criticalDefault: is_string($risk['critical_default'] ?? null) ? $risk['critical_default'] : 'block',
            blockWithoutConfirmation: (bool) ($risk['block_without_confirmation'] ?? true),
        );
    }
}
