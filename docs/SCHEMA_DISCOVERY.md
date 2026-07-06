# Schema Discovery

Schema discovery inspects a Laravel database connection and builds a schema
catalog for documentation, metadata governance and reference-table suggestions.
It is optional and separate from runtime ADP resource exposure.

## Responsibility Boundary

This feature can describe the database. It must not make every table executable
by agents. Runtime execution still belongs to API endpoints, permissions,
FormRequests and `ronu/rest-generic-class`.

Use schema discovery to answer:

- Which tables and views exist?
- Which fields need labels or descriptions?
- Which tables look like lookup/reference tables?
- Which columns are sensitive?
- Which foreign keys can become `reference` metadata?
- Which config overrides should be added for a connection?

## Configuration

Global defaults live in `config/agent-protocol.php`:

```php
'schema_discovery' => [
    'enabled' => true,
    'config_path' => config_path('agent-protocol/schemas'),
    'default_connection' => env('DB_CONNECTION'),
    'include_views' => true,
    'estimate_rows' => false,
    'include_tables' => [],
    'exclude_tables' => ['migrations', 'jobs', 'failed_jobs'],
    'cacheable_row_limit' => 100,
    'cacheable_name_patterns' => ['*_types', '*_statuses', '*_categories'],
    'sensitive_column_patterns' => ['password', '*token*', '*secret*'],
],
```

Connection-specific overrides live in:

```text
config/agent-protocol/schemas/{connection}.php
```

## Commands

Discover a connection:

```bash
php artisan agent:schema:discover mysql
```

Output JSON:

```bash
php artisan agent:schema:discover mysql --json
```

Output Markdown:

```bash
php artisan agent:schema:discover mysql --markdown
```

Generate a connection override file:

```bash
php artisan agent:schema:discover mysql --write-config
```

Export a catalog:

```bash
php artisan agent:schema:export mysql docs/generated/schema.json
php artisan agent:schema:export mysql docs/generated/schema.md --format=markdown
```

Export suggested ADP reference tables:

```bash
php artisan agent:schema:export mysql docs/generated/reference-tables.php --format=reference-config
```

Validate schema metadata:

```bash
php artisan agent:schema:validate mysql
```

## Override Precedence

The effective schema catalog is built in this order:

1. Database introspection.
2. Generated labels/descriptions.
3. Sensitive-field classification.
4. `{connection}.php` overrides.
5. Cacheable/reference-table classification.

Manual config values win over inferred values.

## Cacheable Table Classification

A table is considered a cacheable/reference candidate when:

- it is a base table, not a view;
- its name matches configured lookup patterns; or
- row estimates are enabled and row count is under `cacheable_row_limit`;
- it has a reasonable lookup field such as `name`, `label`, `title`, `code` or
  `slug`.

Explicit config always wins:

```php
'tables' => [
    'departments' => [
        'cacheable' => true,
        'reference_table' => true,
        'lookup_field' => 'name',
    ],
],
```

## Using The Output With ADP

Schema discovery can suggest:

```php
'reference_tables' => [
    'departments' => [
        'resource' => 'hr.department',
        'fields' => ['id', 'name'],
        'lookup_field' => 'name',
        'foreign_keys' => ['department_id'],
        'max_records' => 100,
    ],
],
```

Copy reviewed suggestions into `agent-protocol.reference_tables`. Do not blindly
publish generated suggestions in production without reviewing sensitivity and
permissions.
