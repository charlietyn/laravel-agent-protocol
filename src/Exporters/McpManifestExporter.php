<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Exporters;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

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
                    'annotations' => [
                        'risk' => $operation->risk,
                        'requires_confirmation' => $operation->requiresConfirmation,
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
}
