# Cache

`MetadataRepository` owns cache behavior.

When `agent-protocol.cache.enabled=true`, `get()` returns the cached graph or
compiles and stores it. `refresh()` always recompiles and updates the cache.

```php
'cache' => [
    'enabled' => true,
    'store' => env('CACHE_STORE'),
    'key' => 'agent-protocol:metadata:v1',
    'ttl' => 3600,
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
php artisan agent:discover
```

## Recommendation

Keep cache enabled in production. Metadata compilation may inspect routes,
models, relations and validation rules.
