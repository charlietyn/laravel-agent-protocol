<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Passes;

use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerPass;
use Ronu\LaravelAgentProtocol\DTO\FilterDescriptor;
use Ronu\LaravelAgentProtocol\Metadata\AgentMetadataGraphBuilder;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;

final class FilterDocumentationPass implements MetadataCompilerPass
{
    public function compile(MetadataBuildContext $context, AgentMetadataGraphBuilder $builder): void
    {
        $configuredOperators = (array) $context->config('rest-generic-class.filtering.allowed_operators', [
            '=', '!=', '<', '>', '<=', '>=',
            'like', 'not like',
            'ilike', 'not ilike',
            'in', 'not in',
            'between', 'not between',
            'null', 'not null',
            'exists', 'not exists',
            'date', 'not date',
        ]);
        $operators = array_values(array_filter($configuredOperators, is_string(...)));

        $builder->setFilterDocumentation(new FilterDescriptor(
            operators: $operators,
            parameters: [
                'eq' => 'Legacy equality filters. Shape: {"field": "value"} or {"field": [1,2]}.',
                'attr' => 'Alias of eq used by rest-generic-class.',
                'oper' => 'Structured boolean filter tree using and/or keys and field|operator|value condition strings.',
                'relations' => 'Allowed eager-loaded relations. Relation names must be present in the model RELATIONS constant.',
                'select' => 'Columns to select from the root resource.',
                'pagination' => 'Offset pagination or cursor-style pagination when infinity=true.',
                'orderby' => 'Ordering map. Dot notation supports relation-aware ordering.',
                'hierarchy' => 'Tree output for models that define HIERARCHY_FIELD_ID.',
                '_nested' => 'When true, relation filters also constrain eager-loaded relation payloads.',
            ],
            conditionFormat: 'field|operator|value',
            examples: [
                [
                    'description' => 'Active records created in a date range.',
                    'payload' => [
                        'oper' => [
                            'and' => [
                                'status|=|active',
                                'created_at|between|2026-01-01,2026-01-31',
                            ],
                        ],
                    ],
                ],
                [
                    'description' => 'Load selected relation fields.',
                    'payload' => [
                        'relations' => ['role:id,name'],
                        'select' => ['id', 'name', 'role_id'],
                    ],
                ],
            ],
        ));
    }
}
