# Code Archaeology: RestGenericClass

This document records the repository analysis that shaped the first
implementation of `ronu/laravel-agent-protocol`.

## Repository Inspected

`../RestGenericClass`

Important files:

- `composer.json`
- `config/rest-generic-class.php`
- `src/Core/Controllers/RestController.php`
- `src/Core/Models/BaseModel.php`
- `src/Core/Requests/BaseFormRequest.php`
- `src/Core/Services/BaseService.php`
- `src/Core/Traits/HasDynamicFilter.php`
- `src/Core/Traits/HasDynamicOrderBy.php`
- `src/Core/Traits/ManagesRelations.php`
- `src/Core/Resolvers/RouteMetaResolver.php`
- `documentation/*`
- `tests/Unit/*`

## Existing Capabilities Reused

### CRUD and Runtime Execution

`RestController` and `BaseService` already implement index, show, create,
update, delete, restore, force delete and export paths. ADP does not duplicate
those behaviors. It only describes them as operations.

### Filter Grammar

`HasDynamicFilter` already defines the `oper` grammar:

```text
field|operator|value
```

Supported operator metadata is read from:

```php
config('rest-generic-class.filtering.allowed_operators')
```

### Relations

`BaseModel::RELATIONS` is the existing allowlist used by filtering, eager
loading and relation-aware ordering. ADP exposes this allowlist as relation
metadata instead of discovering arbitrary model methods.

### Scenarios

`BaseFormRequest` exposes:

```php
getAvailableScenarios()
getRulesForScenario(string $scenario)
```

ADP uses these methods through `ValidationInspector`.

### Soft Deletes and Hierarchy

`BaseModel` exposes `getSoftDeleteColumn()` and `HIERARCHY_FIELD_ID`. ADP maps
these to `capabilities.soft_deletes` and `capabilities.hierarchy`.

### Route Metadata

`RouteMetaResolver` confirms that route-derived module/model/action metadata is
already a first-class concern in `RestGenericClass`. ADP implements a lighter
route pass focused on `$modelClass` and controller methods.

## Decisions

1. Build `MetadataCompiler` first.
2. Serve all endpoints from `AgentMetadataGraph`.
3. Keep controllers free of reflection.
4. Make route discovery optional.
5. Keep explicit resource configuration available for APIs that are not route
   discoverable yet.

## Current Limitations

- Relation metadata depends on `RELATIONS`; hidden or undocumented relations stay
  hidden by design.
- Schema nullability is not inferred without a live database connection.
- Semantic business aliases must come from dictionary providers.
- Route discovery requires controllers to expose `$modelClass` as a default
  property.
