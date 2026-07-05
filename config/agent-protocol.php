<?php

declare(strict_types=1);

use Ronu\LaravelAgentProtocol\Exporters\JsonMetadataExporter;

return [
    'protocol_version' => '1.0',

    'routes' => [
        'enabled' => env('AGENT_PROTOCOL_ROUTES_ENABLED', true),
        'prefix' => env('AGENT_PROTOCOL_ROUTE_PREFIX', 'agent'),
        'middleware' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('AGENT_PROTOCOL_ROUTE_MIDDLEWARE', 'api'))
        ))),
    ],

    'cache' => [
        'enabled' => env('AGENT_PROTOCOL_CACHE_ENABLED', true),
        'store' => env('AGENT_PROTOCOL_CACHE_STORE', env('CACHE_STORE')),
        'key' => env('AGENT_PROTOCOL_CACHE_KEY', 'agent-protocol:metadata:v1'),
        'ttl' => (int) env('AGENT_PROTOCOL_CACHE_TTL', 3600),
    ],

    'discovery' => [
        'routes' => true,
        'route_prefixes' => ['api'],
        'default_module' => '--site--',
        'controllers_extending' => [
            'Ronu\\RestGenericClass\\Core\\Controllers\\RestController',
        ],
        'resource_key_strategy' => 'module_model',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicit resources
    |--------------------------------------------------------------------------
    |
    | Route discovery handles controllers that expose a rest-generic-class model.
    | Use this array for resources that are not reachable from routes yet, or to
    | enrich discovered resources with request classes, descriptions or modules.
    |
    | 'security.user' => [
    |     'module' => 'security',
    |     'model' => App\\Models\\User::class,
    |     'request' => App\\Http\\Requests\\UserRequest::class,
    |     'endpoint' => '/api/security/users',
    |     'description' => 'Users managed by the security module.',
    | ],
    */
    'resources' => [],

    'dictionary' => [
        'active' => ['field' => 'status', 'operator' => '=', 'value' => 'active'],
        'inactive' => ['field' => 'status', 'operator' => '=', 'value' => 'inactive'],
        'created between' => ['parameter' => 'oper', 'operator' => 'between'],
        'with relation' => ['parameter' => 'relations'],
    ],

    'documentation' => [
        'errors' => [
            [
                'code' => 'ADP_RESOURCE_NOT_FOUND',
                'status' => 404,
                'message' => 'The requested ADP resource descriptor does not exist.',
            ],
            [
                'code' => 'ADP_OPERATION_NOT_FOUND',
                'status' => 404,
                'message' => 'The requested ADP operation descriptor does not exist for the resource.',
            ],
            [
                'code' => 'ADP_METADATA_INVALID',
                'status' => 422,
                'message' => 'The compiled metadata graph violates the Agent Discovery Protocol contract.',
            ],
        ],
    ],

    'providers' => [
        'resources' => [],
        'dictionary' => [],
        'documentation' => [],
    ],

    'exporters' => [
        'json' => JsonMetadataExporter::class,
    ],
];
