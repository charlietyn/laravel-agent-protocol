<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Validation;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

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

            foreach ($resource->operations as $operation) {
                if ($operation->scenario === '') {
                    $errors[] = "Resource [{$resource->key}] contains an operation without scenario.";
                }

                if ($operation->endpoint === '') {
                    $errors[] = "Resource [{$resource->key}] operation [{$operation->scenario}] has no endpoint.";
                }

                if (! in_array($operation->method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $errors[] = "Resource [{$resource->key}] operation [{$operation->scenario}] has invalid method [{$operation->method}].";
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
