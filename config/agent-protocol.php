<?php

declare(strict_types=1);

use Ronu\LaravelAgentProtocol\Exporters\JsonMetadataExporter;
use Ronu\LaravelAgentProtocol\Exporters\JsonSchemaMetadataExporter;
use Ronu\LaravelAgentProtocol\Exporters\MarkdownMetadataExporter;
use Ronu\LaravelAgentProtocol\Exporters\McpManifestExporter;

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
        'vary' => [
            'headers' => array_values(array_filter(array_map(
                'trim',
                explode(',', env('AGENT_PROTOCOL_CACHE_VARY_HEADERS', 'Accept-Language,X-Tenant-Id'))
            ))),
        ],
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

    'security' => [
        'redact_sensitive_fields' => env('AGENT_PROTOCOL_REDACT_SENSITIVE_FIELDS', true),
        'expose_sensitive_fields' => env('AGENT_PROTOCOL_EXPOSE_SENSITIVE_FIELDS', false),
        'sensitive_fields' => [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'remember_token',
            'api_token',
            'access_token',
            'refresh_token',
            'secret',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ],
        'hidden_fields' => [],
        'public_fields' => [],
        'tenant_header' => env('AGENT_PROTOCOL_TENANT_HEADER', 'X-Tenant-Id'),
        'locale_header' => env('AGENT_PROTOCOL_LOCALE_HEADER', 'Accept-Language'),
        'default_risk' => 'medium',
        'confirmation_required_for' => ['high', 'critical'],
    ],

    'limits' => [
        'max_depth' => env('AGENT_PROTOCOL_MAX_DEPTH'),
        'max_conditions' => env('AGENT_PROTOCOL_MAX_CONDITIONS'),
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
            [
                'code' => 'ADP_INVALID_RELATION',
                'status' => 400,
                'message' => 'The requested relation is not published as an allowed ADP relation.',
            ],
            [
                'code' => 'ADP_INVALID_OPERATOR',
                'status' => 400,
                'message' => 'The requested filter operator is not allowed by the ADP filter contract.',
            ],
            [
                'code' => 'ADP_FILTER_TOO_DEEP',
                'status' => 400,
                'message' => 'The requested filter or orderby relation depth exceeds the published max_depth limit.',
            ],
            [
                'code' => 'ADP_TOO_MANY_CONDITIONS',
                'status' => 400,
                'message' => 'The requested filter exceeds the published max_conditions limit.',
            ],
            [
                'code' => 'ADP_UNAUTHORIZED_METADATA',
                'status' => 401,
                'message' => 'The caller is not authenticated for this metadata context.',
            ],
            [
                'code' => 'ADP_FORBIDDEN_OPERATION',
                'status' => 403,
                'message' => 'The caller is not allowed to execute or inspect this ADP operation.',
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
        'json-schema' => JsonSchemaMetadataExporter::class,
        'markdown' => MarkdownMetadataExporter::class,
        'mcp' => McpManifestExporter::class,
    ],
];
