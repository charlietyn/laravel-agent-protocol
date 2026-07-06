<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Validation;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\Security\OperationRiskClassifier;

final class ProtocolValidator
{
    /**
     * @return array<int, string>
     */
    public function validate(AgentMetadataGraph $graph): array
    {
        $errors = [];
        $resourceKeys = [];

        foreach ($graph->resources as $resource) {
            if (isset($resourceKeys[$resource->key])) {
                $errors[] = "Duplicate resource key [{$resource->key}].";
            }
            $resourceKeys[$resource->key] = true;

            if ($resource->operations === []) {
                $errors[] = "Resource [{$resource->key}] has no operations.";
            }

            $operationScenarios = [];
            foreach ($resource->operations as $operation) {
                if (isset($operationScenarios[$operation->scenario])) {
                    $errors[] = "Resource [{$resource->key}] contains duplicate operation [{$operation->scenario}].";
                }
                $operationScenarios[$operation->scenario] = true;

                if ($operation->scenario === '') {
                    $errors[] = "Resource [{$resource->key}] contains an operation without scenario.";
                }

                if ($operation->endpoint === '') {
                    $errors[] = "Resource [{$resource->key}] operation [{$operation->scenario}] has no endpoint.";
                }

                if (! in_array($operation->method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $errors[] = "Resource [{$resource->key}] operation [{$operation->scenario}] has invalid method [{$operation->method}].";
                }

                if (! in_array($operation->risk, OperationRiskClassifier::VALID, true)) {
                    $errors[] = "Resource [{$resource->key}] operation [{$operation->scenario}] has invalid risk [{$operation->risk}].";
                }

                if (in_array($operation->risk, [OperationRiskClassifier::HIGH, OperationRiskClassifier::CRITICAL], true)
                    && ! $operation->requiresConfirmation) {
                    $errors[] = "Resource [{$resource->key}] operation [{$operation->scenario}] is [{$operation->risk}] risk and must require confirmation.";
                }
            }

            $relationNames = [];
            foreach ($resource->relations as $relation) {
                if (isset($relationNames[$relation->name])) {
                    $errors[] = "Resource [{$resource->key}] contains duplicate relation [{$relation->name}].";
                }
                $relationNames[$relation->name] = true;

                if (! $relation->allowed) {
                    $errors[] = "Resource [{$resource->key}] publishes relation [{$relation->name}] as not allowed.";
                }
            }

            if ($resource->capabilities) {
                foreach ($resource->capabilities->relations as $relation) {
                    if (! isset($relationNames[$relation])) {
                        $errors[] = "Resource [{$resource->key}] capability references missing relation [{$relation}].";
                    }
                }
            }

            foreach ($resource->fields as $field) {
                if ($field->sensitive && $field->visible && ! (bool) ($resource->security['expose_sensitive_fields'] ?? false)) {
                    $errors[] = "Resource [{$resource->key}] publishes sensitive field [{$field->name}] without explicit exposure.";
                }
            }
        }

        foreach ($graph->modules as $module) {
            foreach ($module->resources as $resourceKey) {
                if (! isset($resourceKeys[$resourceKey])) {
                    $errors[] = "Module [{$module->key}] references missing resource [{$resourceKey}].";
                }
            }
        }

        return $errors;
    }
}
