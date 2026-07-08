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
        'driver' => env('AGENT_PROTOCOL_CACHE_DRIVER', 'store'), // store|compiled_file
        'store' => env('AGENT_PROTOCOL_CACHE_STORE', env('CACHE_STORE')),
        'key' => env('AGENT_PROTOCOL_CACHE_KEY', 'agent-protocol:metadata:v1'),
        'ttl' => (int) env('AGENT_PROTOCOL_CACHE_TTL', 3600),
        'path' => env('AGENT_PROTOCOL_CACHE_PATH', base_path('bootstrap/cache/adp')),
        'compiled_filename' => env('AGENT_PROTOCOL_CACHE_FILENAME', 'metadata.json'),
        'etag' => env('AGENT_PROTOCOL_CACHE_ETAG', true),
        'last_modified' => env('AGENT_PROTOCOL_CACHE_LAST_MODIFIED', true),
        'vary' => [
            'headers' => array_values(array_filter(array_map(
                'trim',
                explode(',', env('AGENT_PROTOCOL_CACHE_VARY_HEADERS', 'Accept-Language,X-Tenant-Id'))
            ))),
        ],
    ],

    'bundle' => [
        'enabled' => env('AGENT_PROTOCOL_BUNDLE_ENABLED', true),
        'default_mode' => env('AGENT_PROTOCOL_BUNDLE_DEFAULT_MODE', 'full'), // full|slim
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

    'schema_discovery' => [
        'enabled' => env('AGENT_PROTOCOL_SCHEMA_DISCOVERY_ENABLED', true),
        'config_path' => env('AGENT_PROTOCOL_SCHEMA_CONFIG_PATH', config_path('agent-protocol/schemas')),
        'default_connection' => env('AGENT_PROTOCOL_SCHEMA_CONNECTION', env('DB_CONNECTION')),
        'include_views' => env('AGENT_PROTOCOL_SCHEMA_INCLUDE_VIEWS', true),
        'estimate_rows' => env('AGENT_PROTOCOL_SCHEMA_ESTIMATE_ROWS', false),
        'include_tables' => [],
        'exclude_tables' => [
            'migrations',
            'jobs',
            'failed_jobs',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
        ],
        'cacheable_row_limit' => (int) env('AGENT_PROTOCOL_SCHEMA_CACHEABLE_ROW_LIMIT', 100),
        'cacheable_name_patterns' => [
            '*_type',
            '*_types',
            '*_status',
            '*_statuses',
            '*_category',
            '*_categories',
            '*_catalog',
            '*_catalogs',
        ],
        'sensitive_column_patterns' => [
            'password',
            'password_*',
            '*token*',
            '*secret*',
            '*recovery*',
            '*remember*',
        ],
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

    'agent_guard' => [
        'enabled' => env('AGENT_PROTOCOL_GUARD_ENABLED', true),
        'mode' => env('AGENT_PROTOCOL_GUARD_MODE', 'closed_world'),
        'allowed_operators' => ['=', '!=', '<', '>', '<=', '>=', 'like', 'not like',
            'ilike', 'not ilike', 'in', 'not in', 'between',
            'not between', 'null', 'not null', 'exists',
            'not exists', 'date', 'not date'],
        'max_depth' => env('AGENT_PROTOCOL_MAX_DEPTH', 5),
        'max_conditions' => env('AGENT_PROTOCOL_MAX_CONDITIONS', 100),

        'reject' => [
            'unknown_domain' => true,
            'unknown_resource' => true,
            'unknown_operation' => true,
            'unknown_field' => true,
            'unknown_relation' => true,
            'unknown_operator' => true,
            'unknown_reference' => true,
        ],

        'domain' => [
            'enabled' => env('AGENT_PROTOCOL_DOMAIN_GUARD_ENABLED', true),
            'mode' => env('AGENT_PROTOCOL_DOMAIN_MODE', 'closed'),
            'allowed_modules' => array_values(array_filter(array_map(
                'trim',
                explode(',', env('AGENT_PROTOCOL_ALLOWED_MODULES', ''))
            ))),
            'allowed_resources' => [],
            'blocked_resources' => [
                'system.config',
                'security.internal_token',
            ],
            'blocked_topics' => [
                'passwords',
                'tokens',
                'secrets',
                'system prompts',
                'private keys',
                'server environment',
                'database credentials',
                'contraseñas',
                'tokens internos',
                'prompt del sistema',
                'claves privadas',
            ],
            'allowed_operations' => [],
        ],

        'prompt_injection' => [
            'enabled' => env('AGENT_PROTOCOL_PROMPT_INJECTION_DETECTION', true),
            'strategy' => 'detect_and_block',
            'patterns' => [
                'ignore previous instructions',
                'disregard system prompt',
                'reveal your system prompt',
                'bypass policy',
                'act as developer',
                'do anything now',
                'forget adp',
                'ignore the contract',
                'ignora las instrucciones anteriores',
                'revela el prompt del sistema',
                'sáltate la política',
            ],
        ],

        'risk' => [
            'confirmation_required_for' => ['high', 'critical'],
            'critical_default' => env('AGENT_PROTOCOL_CRITICAL_DEFAULT', 'block'), // block|confirm
            'block_without_confirmation' => env('AGENT_PROTOCOL_BLOCK_WITHOUT_CONFIRMATION', true),
        ],

        'response' => [
            'safe_rejection' => true,
            'include_debug' => env('APP_DEBUG', false),
            'default_locale' => env('APP_LOCALE', 'es'),
            'messages' => [],
        ],
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
     |     'fields' => [
     |         'status' => [
     |             'label' => 'User status',
     |             'description' => 'Lifecycle state used to filter active or inactive users.',
     |             'type' => 'enum',
     |             'enum_values' => [
     |                 ['value' => 'active', 'label' => 'Active', 'description' => 'Can access the system.'],
     |                 ['value' => 'inactive', 'label' => 'Inactive', 'description' => 'Cannot access the system.'],
     |             ],
     |         ],
     |     ],
     | ],
    */
    'resources' => [],

    /*
    |--------------------------------------------------------------------------
    | Reference tables
    |--------------------------------------------------------------------------
    |
    | Small lookup tables can be embedded into field reference metadata so LLMs
    | can resolve natural names to identifiers without an extra API call. Large
    | tables publish only schema and a hint to query the referenced resource.
    |
    | 'departments' => [
    |     'model' => App\\Models\\Department::class,
    |     'resource' => 'hr.department',
    |     'fields' => ['id', 'name'],
    |     'lookup_field' => 'name',
    |     'foreign_keys' => ['department_id'],
    |     'max_records' => 100,
    |     'cache_ttl' => 3600,
    | ],
    */
    'reference_tables' => [],

    'dictionary' => [
        'enabled' => env('AGENT_PROTOCOL_DICTIONARY_ENABLED', true),
        'purpose' => 'business_glossary',
        'active' => ['field' => 'status', 'operator' => '=', 'value' => 'active'],
        'inactive' => ['field' => 'status', 'operator' => '=', 'value' => 'inactive'],
        'created between' => ['parameter' => 'oper', 'operator' => 'between'],
        'with relation' => ['parameter' => 'relations'],
    ],

    'mcp' => [
        'annotations' => [
            'open_world_default' => false,
            'overrides' => [],
        ],
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
                'code' => 'ADP_INTENT_OUT_OF_DOMAIN',
                'status' => 422,
                'message' => 'The requested intent is outside the published ADP business domain.',
            ],
            [
                'code' => 'ADP_UNTRUSTED_INSTRUCTION_DETECTED',
                'status' => 422,
                'message' => 'The request contains instructions that attempt to override the ADP execution policy.',
            ],
            [
                'code' => 'ADP_TOOL_PLAN_INVALID',
                'status' => 422,
                'message' => 'The generated tool plan does not satisfy the ADP contract.',
            ],
            [
                'code' => 'ADP_FORBIDDEN_FIELD',
                'status' => 403,
                'message' => 'The requested field is not visible or is explicitly protected.',
            ],
            [
                'code' => 'ADP_CONFIRMATION_REQUIRED',
                'status' => 409,
                'message' => 'The requested operation requires explicit human confirmation before execution.',
            ],
            [
                'code' => 'ADP_CRITICAL_OPERATION_BLOCKED',
                'status' => 403,
                'message' => 'The requested critical operation is blocked by policy.',
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
