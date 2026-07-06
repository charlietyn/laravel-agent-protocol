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

## Cache

ADP metadata is compiled into an `AgentMetadataGraph` and cached. Cache keys can
vary by configured headers such as `Accept-Language` and `X-Tenant-Id`.

```bash
php artisan agent:cache
php artisan agent:clear
```

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
- [Protocol](docs/PROTOCOL.md)
- [Endpoints](docs/ENDPOINTS.md)
- [Cache](docs/CACHE.md)
- [Security](docs/SECURITY.md)
- [Readiness](docs/READINESS.md)
- [CLI](docs/CLI.md)
- [Exporters](docs/EXPORTERS.md)
- [Generated Docs](docs/GENERATED_DOCS.md)
- [MCP and n8n](docs/MCP_N8N.md)
