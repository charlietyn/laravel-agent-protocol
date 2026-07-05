# Quickstart

Add a resource to `config/agent-protocol.php`:

```php
'resources' => [
    'security.user' => [
        'module' => 'security',
        'model' => App\Models\User::class,
        'request' => App\Http\Requests\UserRequest::class,
        'endpoint' => '/api/security/users',
    ],
],
```

Compile metadata:

```bash
php artisan agent:cache
```

Inspect it:

```bash
curl http://localhost/agent/resources/security.user
curl http://localhost/agent/resources/security.user/operations/create
curl http://localhost/agent/documentation/filter
```

The resource descriptor will include model fields, validation rules per scenario,
relations declared in `RELATIONS`, operation capabilities and filter
documentation.
