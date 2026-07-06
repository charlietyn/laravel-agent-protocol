# Cache

`MetadataRepository` owns cache behavior.

When `agent-protocol.cache.enabled=true`, `get()` returns the cached graph or
compiles and stores it. `refresh()` always recompiles and updates the cache.

```php
'cache' => [
    'enabled' => true,
    'driver' => 'store',
    'store' => env('CACHE_STORE'),
    'key' => 'agent-protocol:metadata:v1',
    'ttl' => 3600,
    'path' => base_path('bootstrap/cache/adp'),
    'compiled_filename' => 'metadata.json',
    'vary' => [
        'headers' => ['Accept-Language', 'X-Tenant-Id'],
    ],
],
```

When configured, cache keys include a stable hash of the selected request
headers. This supports metadata that varies by tenant or locale.

## Commands

```bash
php artisan agent:cache
php artisan agent:clear
php artisan agent:cache --tenant=7
php artisan agent:clear --tenant=7
php artisan agent:cache --only=references
php artisan agent:discover
```

## Recommendation

Keep cache enabled in production. Metadata compilation may inspect routes,
models, relations and validation rules.

## Compiled File Cache

Set `agent-protocol.cache.driver=compiled_file` to store the graph as a
dedicated JSON file instead of using the application cache store. This prevents
ordinary application cache clears from removing ADP metadata.

Compiled files are written under `agent-protocol.cache.path`. When tenant or
locale variation is provided, the filename receives a stable hash.

## Consumer Cache

Consumers such as n8n and MCP servers should cache `/agent/bundle` locally.
Responses include `ETag` and `Last-Modified` headers so consumers can revalidate
without repeating full discovery.

For production cache flows, tenant variation and reference-table guidance, see
[Guia Avanzada De Uso E Integracion](GUIA_AVANZADA.md).
