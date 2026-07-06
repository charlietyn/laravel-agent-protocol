<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Exporters;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;

final class McpManifestExporter implements MetadataExporter
{
    public function export(AgentMetadataGraph $graph): string
    {
        $resources = [];
        $tools = [];

        foreach ($graph->resources as $resource) {
            $resources[] = [
                'uri' => 'adp://resources/'.$resource->key,
                'name' => $resource->key,
                'description' => $resource->description ?? 'ADP resource '.$resource->key,
                'mimeType' => 'application/json',
            ];

            foreach ($resource->operations as $operation) {
                $tools[] = [
                    'name' => $this->toolName($operation->scenario, $resource->key),
                    'description' => $operation->description,
                    'inputSchema' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                    'annotations' => $this->annotations($operation),
                    'x-adp' => [
                        'risk_level' => $operation->risk,
                        'requires_confirmation' => $operation->requiresConfirmation,
                        'permissions' => $operation->permissions,
                        'side_effects' => $operation->sideEffects,
                        'source' => 'adp://resources/'.$resource->key.'/operations/'.$operation->scenario,
                    ],
                ];
            }
        }

        return (string) json_encode([
            'protocol' => 'mcp-derived-from-adp',
            'adp_version' => $graph->protocolVersion,
            'resources' => $resources,
            'tools' => $tools,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolName(string $operation, string $resource): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $operation.'_'.$resource) ?: $operation);
    }

    /**
     * @return array<string, bool>
     */
    private function annotations(OperationDescriptor $operation): array
    {
        $annotations = [
            'readOnlyHint' => $this->readOnly($operation),
            'destructiveHint' => $this->destructive($operation),
            'idempotentHint' => $this->idempotent($operation),
            'openWorldHint' => false,
        ];

        foreach ($operation->annotations as $key => $value) {
            if (is_string($key) && is_bool($value) && array_key_exists($key, $annotations)) {
                $annotations[$key] = $value;
            }
        }

        return $annotations;
    }

    private function readOnly(OperationDescriptor $operation): bool
    {
        return in_array($operation->scenario, ['query', 'index', 'show', 'query_one'], true)
            || strtoupper($operation->method) === 'GET' && ! str_starts_with($operation->scenario, 'export_');
    }

    private function destructive(OperationDescriptor $operation): bool
    {
        $scenario = strtolower($operation->scenario);

        return str_contains($scenario, 'delete')
            || str_contains($scenario, 'bulk_update')
            || str_contains($scenario, 'multiple')
            || in_array($operation->risk, ['high', 'critical'], true) && ! str_contains($scenario, 'restore');
    }

    private function idempotent(OperationDescriptor $operation): bool
    {
        $scenario = strtolower($operation->scenario);

        if ($this->readOnly($operation)) {
            return true;
        }

        if (str_contains($scenario, 'create') || str_contains($scenario, 'bulk_create')) {
            return false;
        }

        return in_array(strtoupper($operation->method), ['PUT', 'PATCH', 'DELETE'], true)
            || str_contains($scenario, 'restore');
    }
}
