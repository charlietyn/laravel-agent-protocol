# Providers

The package exposes three extension contracts.

## ResourceProvider

```php
interface ResourceProvider
{
    public function resources(): iterable;
}
```

Use it to publish resources from modules or package-specific registries.

## DictionaryProvider

```php
interface DictionaryProvider
{
    public function dictionary(): array;
}
```

Use it to translate human terms to filter hints.

## DocumentationProvider

```php
interface DocumentationProvider
{
    public function documents(): iterable;
}
```

Use it to add protocol or application documentation descriptors.

Register providers in `config/agent-protocol.php`:

```php
'providers' => [
    'resources' => [App\Agent\BillingResourceProvider::class],
    'dictionary' => [App\Agent\HrDictionaryProvider::class],
    'documentation' => [App\Agent\ApiDocumentationProvider::class],
],
```
