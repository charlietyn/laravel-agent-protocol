# Scenarios

Scenarios are operation variants for a resource. They are not limited to CRUD.

Examples:

- `query`
- `create`
- `update`
- `bulk_create`
- `bulk_update`
- `restore`
- `force_delete`
- `export_excel`
- `change_password`
- `reset_password`

`ValidationInspector` supports request classes with:

```php
getAvailableScenarios(): array
getRulesForScenario(string $scenario): array
```

This matches the scenario model already present in `rest-generic-class`
`BaseFormRequest`.

`OperationFactory` maps well-known scenarios to HTTP methods and endpoints. Any
unknown scenario is represented as `POST /{endpoint}/{scenario}`.
