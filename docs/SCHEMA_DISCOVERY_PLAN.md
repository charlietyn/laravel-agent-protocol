# Plan: Database Schema Discovery And Connection-Level Metadata

## Summary

Add an optional schema-discovery layer to `ronu/laravel-agent-protocol` that can
inspect a configured database connection and generate a full schema catalog:
schemas/modules, tables, views, columns, indexes, foreign keys, cacheable-table
candidates and table/field descriptions.

This is a valid responsibility for the library when treated as metadata
drafting and governance tooling. It must not bypass ADP resources, route
discovery, FormRequests, permissions or `ronu/rest-generic-class`.

## Design

- Add schema commands under `agent:schema:*`.
- Store manual overrides per connection in
  `config/agent-protocol/schemas/{connection}.php`.
- Keep global defaults in `agent-protocol.schema_discovery`.
- Use database introspection as raw truth.
- Let `{connection}.php` override descriptions, modules, exposure, semantic
  labels, cacheability and reference-table decisions.
- Keep schema metadata separate from `AgentMetadataGraph` by default.
- Generate suggestions for `reference_tables`; do not auto-publish all tables as
  executable ADP resources.

## Commands

```bash
php artisan agent:schema:discover mysql
php artisan agent:schema:discover mysql --json
php artisan agent:schema:discover mysql --markdown
php artisan agent:schema:discover mysql --suggest-reference-tables
php artisan agent:schema:discover mysql --write-config
php artisan agent:schema:export mysql docs/generated/schema.json
php artisan agent:schema:export mysql docs/generated/schema.md --format=markdown
php artisan agent:schema:validate mysql
```

## Override File Shape

```php
return [
    'module' => 'billing',

    'tables' => [
        'invoice_statuses' => [
            'module' => 'billing',
            'label' => 'Invoice statuses',
            'description' => 'Reference table for invoice lifecycle states.',
            'expose' => true,
            'cacheable' => true,
            'reference_table' => true,
            'resource' => 'billing.invoice-status',
            'lookup_field' => 'name',
            'max_records' => 50,
        ],
    ],

    'columns' => [
        'invoices.status_id' => [
            'label' => 'Invoice status',
            'description' => 'Current lifecycle state of the invoice.',
            'references' => [
                'foreign_table' => 'invoice_statuses',
                'columns' => ['status_id'],
                'foreign_columns' => ['id'],
            ],
        ],
    ],
];
```

## Safety

- Do not expose every table as ADP resource automatically.
- Do not include row data except for explicitly cacheable/reference tables.
- Redact sensitive fields using configured patterns.
- Treat views as read-only schema metadata.
- Keep live DB introspection out of normal `/agent` runtime endpoints.

## Tests

- Override merge precedence.
- Cacheable/reference table classification.
- Sensitive column detection.
- Command JSON/Markdown/config output.
- Config generation without losing existing manual values.
- SQLite fallback introspection.
