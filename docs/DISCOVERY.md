# Discovery

Discovery currently has two sources.

## Explicit Resources

`ConfiguredResourcePass` reads `agent-protocol.resources` and compiles each
definition.

It uses:

- `ValidationInspector` for scenario rules.
- `ModelInspector` for fields, table, primary key, casts and model metadata.
- `RelationInspector` for `RELATIONS`.
- `OperationFactory` for ADP operation descriptors.

## Route Resources

`RouteResourcePass` scans Laravel routes when
`agent-protocol.discovery.routes=true`.

It accepts controllers that extend any class configured in
`agent-protocol.discovery.controllers_extending`. The default is:

```php
Ronu\RestGenericClass\Core\Controllers\RestController::class
```

For each matching controller it reads the default `$modelClass` property and
maps controller methods to scenarios.

## What Is Not Done

The package does not infer arbitrary business meaning from database names. Use
the dictionary provider for semantic aliases such as "absent" or "active".
