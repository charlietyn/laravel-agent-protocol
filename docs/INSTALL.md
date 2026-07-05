# Installation

```bash
composer require ronu/laravel-agent-protocol
php artisan vendor:publish --tag=agent-protocol-config
```

The package auto-discovers its Laravel service provider:

```php
Ronu\LaravelAgentProtocol\Providers\AgentProtocolServiceProvider::class
```

## Requirements

- PHP `^8.3`
- Laravel 11 or 12
- A Laravel API, preferably using `ronu/rest-generic-class`

## Recommended First Commands

```bash
php artisan agent:validate
php artisan agent:cache
```

If `agent:validate` reports missing resources, add explicit resources in
`config/agent-protocol.php` or make sure your controllers extend
`RestController` and expose a `$modelClass` property.
