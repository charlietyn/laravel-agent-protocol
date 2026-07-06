# Migration From Manual Prompts

This guide targets applications already using `ronu/rest-generic-class` and
manual prompts or static docs for agents.

## Steps

1. Install and publish config.

```bash
composer require ronu/laravel-agent-protocol
php artisan vendor:publish --tag=agent-protocol-config
```

2. Run discovery.

```bash
php artisan agent:discover
php artisan agent:validate
```

3. Add semantic metadata for ambiguous fields.

Start with fields commonly used in natural language: statuses, types, roles,
departments, categories, countries and business identifiers.

4. Configure small reference tables.

Embed small lookup tables with `reference_tables`. Keep large catalogs as
`complete=false` so the agent queries the referenced resource first.

5. Enable cache for production.

Use `cache.driver=compiled_file` when metadata should survive ordinary Laravel
cache clears.

```bash
php artisan agent:cache
```

6. Replace prompt blocks with ADP discovery.

Consumers should load `/agent/bundle?mode=full` once, cache it, then revalidate
with `ETag` or `Last-Modified`.

7. Track metrics.

Use `docs/METRICS.md` to compare token use, prompt maintenance and operation
success before and after migration.
