# Ronu Laravel Agent Protocol

`ronu/laravel-agent-protocol` publishes Laravel API metadata as an Agent
Discovery Protocol (ADP) graph for LLM agents, n8n workflows, MCP adapters,
SDKs and generated documentation.

The package is metadata-only. It does not execute business operations, does not
replace `ronu/rest-generic-class`, and does not expose the database directly.
The backend remains the source of truth; ADP describes what the backend already
supports.

## Requirements

- PHP `^8.3`
- Laravel 11 or 12
- Recommended: `ronu/rest-generic-class`

## Install

```bash
composer require ronu/laravel-agent-protocol
php artisan vendor:publish --tag=agent-protocol-config
```

## Configure A Resource

```php
// config/agent-protocol.php

'resources' => [
    'security.user' => [
        'module' => 'security',
        'model' => App\Models\User::class,
        'request' => App\Http\Requests\UserRequest::class,
        'endpoint' => '/api/security/users',
        'description' => 'Users managed by the security module.',
        'permissions' => ['security.user.view'],
        'operations' => [
            'delete' => [
                'method' => 'DELETE',
                'endpoint' => '/api/security/users/{id}',
                'permissions' => ['security.user.delete'],
            ],
        ],
    ],
],
```

Route discovery can also detect controllers extending
`Ronu\RestGenericClass\Core\Controllers\RestController`.

## Endpoints

```text
GET /agent
GET /agent/bundle?mode=full
GET /agent/bundle?mode=slim
GET /agent/modules
GET /agent/resources
GET /agent/resources/{resource}
GET /agent/resources/{resource}/operations
GET /agent/resources/{resource}/operations/{scenario}
GET /agent/documentation/filter
GET /agent/documentation/errors
GET /agent/dictionary
```

Each resource includes fields, relations, operations, capabilities, filters,
security metadata and a readiness score. Each operation includes method,
endpoint, validation, risk and whether human confirmation is required.

Fields can include `label`, `description`, `enum_values` and `reference`
metadata so LLMs infer intent from backend-published structure instead of a
hand-maintained synonym dictionary.

## Security Defaults

The package redacts sensitive fields from resource field lists by default:
`password`, tokens, secrets and two-factor recovery data are not published unless
explicitly allowed.

Operations are classified as:

- `low`: query, show and discovery metadata
- `medium`: create, update and controlled exports
- `high`: bulk operations, delete, restore and role/user assignment
- `critical`: force delete, password changes and global permission operations

`high` and `critical` operations require `requires_confirmation=true`.

## ADP Agent Guard

`ADP Agent Guard` validates model-generated tool plans before n8n, MCP adapters
or custom agents call the real Laravel API. It is deterministic PHP validation;
it does not consume LLM tokens and does not execute HTTP requests.

It blocks:

- out-of-domain prompts;
- invented resources, operations, fields, relations or operators;
- hidden or sensitive fields;
- prompt-hijacking signals;
- high-risk operations without confirmation;
- critical operations when policy says they are blocked.

```php
$result = app(\Ronu\LaravelAgentProtocol\Security\AgentGuard\ToolExecutionGuard::class)
    ->authorize($intentPlan, $graph, $agentContext);
```

See [ADP Agent Guard](docs/AGENT_GUARD.md) for examples, n8n/MCP flow and token-cost guidance.

## Cache

ADP metadata is compiled into an `AgentMetadataGraph` and cached. Cache keys can
vary by configured headers such as `Accept-Language` and `X-Tenant-Id`.

```bash
php artisan agent:cache
php artisan agent:clear
php artisan agent:cache --tenant=7
php artisan agent:clear --tenant=7
```

Set `AGENT_PROTOCOL_CACHE_DRIVER=compiled_file` to store metadata as a dedicated
compiled JSON file under `bootstrap/cache/adp`.

## CLI

```bash
php artisan agent:discover
php artisan agent:discover --json
php artisan agent:validate
php artisan agent:cache
php artisan agent:clear
php artisan agent:export agent-metadata.json --format=json
php artisan agent:export adp-schema.json --format=json-schema
php artisan agent:export mcp-manifest.json --format=mcp
php artisan agent:docs docs/generated
```

## Exporters

Supported export formats:

- `json`: native ADP graph
- `json-schema`: operation input schemas derived from validation rules
- `markdown`: human documentation generated from metadata
- `mcp`: MCP-style resources/tools manifest derived from ADP

MCP and n8n execution adapters should live outside this core package. This
package only prepares the metadata they need.

## Quality

```bash
composer quality
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

The test suite covers DTO serialization, compiler behavior, validation,
exporters, endpoints, CLI commands, security redaction, risk metadata and filter
limits.

## Documentation

- [Install](docs/INSTALL.md)
- [Quickstart](docs/QUICKSTART.md)
- [Advanced Usage Guide](docs/GUIA_AVANZADA.md)
- [Protocol](docs/PROTOCOL.md)
- [Endpoints](docs/ENDPOINTS.md)
- [Cache](docs/CACHE.md)
- [Security](docs/SECURITY.md)
- [ADP Agent Guard](docs/AGENT_GUARD.md)
- [Readiness](docs/READINESS.md)
- [CLI](docs/CLI.md)
- [Exporters](docs/EXPORTERS.md)
- [Generated Docs](docs/GENERATED_DOCS.md)
- [MCP and n8n](docs/MCP_N8N.md)
- [Schema Discovery](docs/SCHEMA_DISCOVERY.md)
- [ADP Spec](docs/spec/ADP.md)
- [End-To-End Example](docs/END_TO_END.md)
- [Migration](docs/MIGRATION.md)
- [Success Metrics](docs/METRICS.md)
- [Release and Publishing](docs/RELEASE.md)
