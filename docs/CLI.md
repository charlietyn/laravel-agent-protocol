# CLI

The package exposes Artisan commands for discovery, validation, cache, export
and generated documentation.

```bash
php artisan agent:discover
php artisan agent:discover --json
php artisan agent:validate
php artisan agent:cache
php artisan agent:clear
php artisan agent:cache --tenant=7
php artisan agent:clear --tenant=7
php artisan agent:cache --only=references
php artisan agent:export agent-metadata.json --format=json
php artisan agent:export adp-schema.json --format=json-schema
php artisan agent:export adp.md --format=markdown
php artisan agent:export mcp-manifest.json --format=mcp
php artisan agent:docs docs/generated
php artisan agent:schema:discover mysql
php artisan agent:schema:export mysql schema-catalog.json
php artisan agent:schema:validate mysql
```

## Commands

| Command | Purpose |
|---|---|
| `agent:discover` | Compile metadata without writing cache. |
| `agent:validate` | Compile and validate the ADP graph. |
| `agent:cache` | Compile, validate and store the graph in cache. |
| `agent:clear` | Clear the configured ADP cache entry. |
| `agent:export` | Export the graph in a configured format. |
| `agent:docs` | Generate Markdown files from compiled metadata. |
| `agent:schema:discover` | Inspect a database connection and print schema metadata. |
| `agent:schema:export` | Export a schema catalog as JSON, Markdown or reference-table config. |
| `agent:schema:validate` | Validate discovered schema metadata and connection overrides. |

CI should run `agent:validate` together with the test suite.

`agent:cache` and `agent:clear` accept `--tenant` and `--locale` so compiled
metadata can vary by the same headers used at runtime. `--only=references`
documents the intent to refresh reference metadata; the current implementation
rebuilds the graph because reference values are compiled into resource fields.
