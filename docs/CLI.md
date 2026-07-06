# CLI

The package exposes Artisan commands for discovery, validation, cache, export
and generated documentation.

```bash
php artisan agent:discover
php artisan agent:discover --json
php artisan agent:validate
php artisan agent:cache
php artisan agent:clear
php artisan agent:export agent-metadata.json --format=json
php artisan agent:export adp-schema.json --format=json-schema
php artisan agent:export adp.md --format=markdown
php artisan agent:export mcp-manifest.json --format=mcp
php artisan agent:docs docs/generated
```

## Commands

| Command | Purpose |
|---|---|
| `agent:discover` | Compile metadata without writing cache. |
| `agent:validate` | Compile and validate the ADP graph. |
| `agent:cache` | Compile, validate and store the graph in cache. |
| `agent:clear` | Clear the configured ADP cache key. |
| `agent:export` | Export the graph in a configured format. |
| `agent:docs` | Generate Markdown files from compiled metadata. |

CI should run `agent:validate` together with the test suite.
