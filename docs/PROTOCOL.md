# Protocol

The protocol envelope returned by `GET /agent` contains:

```json
{
  "protocol": "adp",
  "protocol_version": "1.0",
  "generated_at": "2026-07-05T00:00:00+00:00",
  "implementation": {
    "package": "ronu/laravel-agent-protocol",
    "framework": "laravel",
    "integration": "ronu/rest-generic-class"
  },
  "modules": [],
  "resources": [],
  "documentation": [],
  "filter": {},
  "dictionary": {}
}
```

`GET /agent/bundle?mode=full` returns the same graph with bundle metadata and
inline reference values. `GET /agent/bundle?mode=slim` removes
`reference.inline_values` while keeping the schema and lookup hints.

For implementation guidance, configuration examples and integration patterns,
see [Guia Avanzada De Uso E Integracion](GUIA_AVANZADA.md).

## Resource Contract

Each resource includes:

- `key`
- `module`
- `name`
- `model`
- `table`
- `primary_key`
- `fields`
- `relations`
- `operations`
- `capabilities`
- `meta`

## Field Contract

Each field includes the original technical shape plus optional semantic metadata:

- `name`
- `type`
- `nullable`
- `fillable`
- `cast`
- `validation_rules`
- `filterable`
- `selectable`
- `sensitive`
- `visible`
- `operators`
- `label`
- `description`
- `enum_values`
- `reference`
- `examples`
- `meta`

Enums should use `enum_values` objects with `value`, `label` and optional
`description`. Foreign keys should use `reference` metadata with `resource`,
`lookup_field`, `display_fields`, `inline_values`, `complete`, `max_records` and
`hint`.

## Operation Contract

Each operation includes:

- `scenario`
- `method`
- `endpoint`
- `description`
- `validation`
- `capabilities`
- `request`
- `response`
- `source`
- `risk`
- `requires_confirmation`
- `permissions`
- `security`
- `side_effects`
- `annotations`

## Dictionary Contract

`/agent/dictionary` is an optional business glossary. Core semantic inference
should come from field labels, descriptions, enums, relations and reference
metadata.
