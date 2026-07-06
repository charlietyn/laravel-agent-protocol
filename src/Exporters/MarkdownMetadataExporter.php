<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Exporters;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;

final class MarkdownMetadataExporter implements MetadataExporter
{
    public function export(AgentMetadataGraph $graph): string
    {
        return implode("\n\n", [
            $this->overview($graph),
            $this->resources($graph),
            $this->filters($graph),
            $this->errors($graph),
        ])."\n";
    }

    /**
     * @return array<string, string>
     */
    public function exportDocuments(AgentMetadataGraph $graph): array
    {
        return [
            'overview.md' => $this->overview($graph)."\n",
            'resources.md' => $this->resources($graph)."\n",
            'filters.md' => $this->filters($graph)."\n",
            'errors.md' => $this->errors($graph)."\n",
            'mcp-tools.md' => (new McpManifestExporter)->export($graph)."\n",
        ];
    }

    private function overview(AgentMetadataGraph $graph): string
    {
        return implode("\n", [
            '# Agent Discovery Protocol',
            '',
            '- Protocol: `'.$graph->protocolVersion.'`',
            '- Modules: `'.count($graph->modules).'`',
            '- Resources: `'.count($graph->resources).'`',
            '- Generated at: `'.$graph->generatedAt->format(DATE_ATOM).'`',
        ]);
    }

    private function resources(AgentMetadataGraph $graph): string
    {
        $lines = ['# Resources'];

        foreach ($graph->resources as $resource) {
            $lines[] = '';
            $lines[] = '## `'.$resource->key.'`';
            $lines[] = '';
            $lines[] = '- Module: `'.$resource->module.'`';
            $lines[] = '- Endpoint: `'.($resource->endpoint ?? '').'`';
            $lines[] = '- Readiness: `'.($resource->readiness['score'] ?? 0).'` (`'.($resource->readiness['status'] ?? 'unknown').'`)';
            $lines[] = '- Fields: `'.count($resource->fields).'`';
            $lines[] = '- Relations: `'.count($resource->relations).'`';
            $lines[] = '';
            $lines[] = '| Scenario | Method | Endpoint | Risk | Confirmation |';
            $lines[] = '|---|---|---|---|---|';

            foreach ($resource->operations as $operation) {
                $lines[] = sprintf(
                    '| `%s` | `%s` | `%s` | `%s` | `%s` |',
                    $operation->scenario,
                    $operation->method,
                    $operation->endpoint,
                    $operation->risk,
                    $operation->requiresConfirmation ? 'yes' : 'no',
                );
            }

            $fieldLines = $this->fieldLines($resource);
            if ($fieldLines !== []) {
                $lines[] = '';
                $lines[] = 'Fields:';
                $lines = [...$lines, ...$fieldLines];
            }
        }

        return implode("\n", $lines);
    }

    private function filters(AgentMetadataGraph $graph): string
    {
        $filter = $graph->filterDocumentation;

        return implode("\n", [
            '# Filters',
            '',
            '- Condition format: `'.($filter?->conditionFormat ?? 'field|operator|value').'`',
            '- Operators: `'.implode('`, `', $filter?->operators ?? []).'`',
            '- Max depth: `'.($filter?->limits['max_depth'] ?? 'n/a').'`',
            '- Max conditions: `'.($filter?->limits['max_conditions'] ?? 'n/a').'`',
            '- Strict relations: `'.(($filter?->strictRelations ?? true) ? 'true' : 'false').'`',
        ]);
    }

    private function errors(AgentMetadataGraph $graph): string
    {
        $lines = ['# ADP Errors'];

        foreach ($graph->documentation as $document) {
            if ($document->slug !== 'errors') {
                continue;
            }

            $errors = $document->payload['errors'] ?? [];
            if (! is_array($errors)) {
                continue;
            }

            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }

                $lines[] = '- `'.($error['code'] ?? 'unknown').'`: '.($error['message'] ?? '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function fieldLines(ResourceDescriptor $resource): array
    {
        $lines = [];

        foreach ($resource->fields as $field) {
            $lines[] = sprintf(
                '- `%s` `%s`%s',
                $field->name,
                $field->type,
                $field->fillable ? ' fillable' : '',
            );
        }

        return $lines;
    }
}
