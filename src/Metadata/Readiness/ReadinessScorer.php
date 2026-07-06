<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Readiness;

use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;

final class ReadinessScorer
{
    /**
     * @return array{score: int, status: string, decision: string, checks: array<string, bool>}
     */
    public function score(ResourceDescriptor $resource): array
    {
        $checks = [
            'has_endpoint' => $resource->endpoint !== null && $resource->endpoint !== '',
            'has_operations' => $resource->operations !== [],
            'has_fields' => $resource->fields !== [],
            'has_validation' => $this->hasValidation($resource),
            'has_filter_contract' => $resource->filters !== [],
            'has_security_metadata' => $resource->security !== [],
            'has_risk_metadata' => $this->hasRiskMetadata($resource),
            'has_dictionary_or_examples' => $resource->examples !== [] || isset($resource->security['dictionary']),
        ];

        $score = 0;
        $score += $checks['has_endpoint'] ? 10 : 0;
        $score += $checks['has_operations'] ? 20 : 0;
        $score += $checks['has_fields'] ? 15 : 0;
        $score += $checks['has_validation'] ? 15 : 0;
        $score += $checks['has_filter_contract'] ? 10 : 0;
        $score += $checks['has_security_metadata'] ? 15 : 0;
        $score += $checks['has_risk_metadata'] ? 10 : 0;
        $score += $checks['has_dictionary_or_examples'] ? 5 : 0;

        [$status, $decision] = $this->decision($score);

        return [
            'score' => min(100, $score),
            'status' => $status,
            'decision' => $decision,
            'checks' => $checks,
        ];
    }

    private function hasValidation(ResourceDescriptor $resource): bool
    {
        foreach ($resource->operations as $operation) {
            if ($operation->validation !== null && $operation->validation->rules !== []) {
                return true;
            }
        }

        return false;
    }

    private function hasRiskMetadata(ResourceDescriptor $resource): bool
    {
        foreach ($resource->operations as $operation) {
            if ($operation->risk !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function decision(int $score): array
    {
        if ($score >= 90) {
            return ['agent_ready', 'can_expose_low_risk_operations_for_automation'];
        }

        if ($score >= 70) {
            return ['partially_ready', 'allow_queries_and_require_confirmation_for_mutations'];
        }

        if ($score >= 50) {
            return ['documentation_only', 'use_for_discovery_and_human_documentation'];
        }

        return ['not_ready', 'do_not_publish_as_executable_tool'];
    }
}
