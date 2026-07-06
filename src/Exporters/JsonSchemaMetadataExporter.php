<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Exporters;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;

final class JsonSchemaMetadataExporter implements MetadataExporter
{
    public function export(AgentMetadataGraph $graph): string
    {
        $schemas = [];

        foreach ($graph->resources as $resource) {
            foreach ($resource->operations as $operation) {
                $schemas[$resource->key.'.'.$operation->scenario] = $this->schemaFor($operation);
            }
        }

        return (string) json_encode([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => 'Agent Discovery Protocol operation input schemas',
            'type' => 'object',
            'properties' => $schemas,
            'additionalProperties' => false,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFor(OperationDescriptor $operation): array
    {
        $properties = [];
        $required = [];

        foreach ($operation->validation?->rules ?? [] as $field => $rules) {
            $properties[$field] = [
                'type' => $this->typeFor($rules),
                'description' => implode('|', $rules),
            ];

            if (in_array('required', $rules, true)) {
                $required[] = $field;
            }
        }

        if ($properties === [] && $operation->method === 'GET') {
            $properties = [
                'oper' => ['type' => 'object'],
                'select' => ['type' => 'array', 'items' => ['type' => 'string']],
                'relations' => ['type' => 'array', 'items' => ['type' => 'string']],
            ];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => true,
            'x-adp-risk' => $operation->risk,
            'x-adp-requires-confirmation' => $operation->requiresConfirmation,
        ];
    }

    /**
     * @param  array<int, string>  $rules
     */
    private function typeFor(array $rules): string
    {
        if (array_intersect($rules, ['integer', 'int']) !== []) {
            return 'integer';
        }

        if (array_intersect($rules, ['numeric', 'decimal']) !== []) {
            return 'number';
        }

        if (in_array('array', $rules, true)) {
            return 'array';
        }

        if (in_array('boolean', $rules, true)) {
            return 'boolean';
        }

        return 'string';
    }
}
