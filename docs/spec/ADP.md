# Agent Discovery Protocol Spec

This document describes ADP as a framework-agnostic metadata contract. Laravel,
Eloquent, FormRequest and rest-generic-class are implementation details of this
package, not protocol requirements.

## Envelope

```json
{
  "protocol": "adp",
  "protocol_version": "1.0",
  "generated_at": "2026-07-06T00:00:00+00:00",
  "implementation": {},
  "capabilities": [],
  "links": {},
  "modules": [],
  "resources": [],
  "documentation": [],
  "filter": {},
  "dictionary": {}
}
```

## Resource

A resource is an agent-visible business object.

Required fields:

- `key`: stable resource identifier, for example `security.user`.
- `module`: logical module identifier.
- `name`: human-readable short name.
- `fields`: published field descriptors.
- `relations`: allowed relation descriptors.
- `operations`: executable or documentable operation descriptors.

Implementation-specific source data such as model class names may appear in
`meta`, but consumers must not depend on framework-specific names.

## Field

Fields describe the semantic shape an agent can use for filtering, selecting and
payload generation.

Important fields:

- `name`: backend field name.
- `type`: scalar type, `enum`, `foreign_key` or implementation-defined type.
- `label`: short human-readable name.
- `description`: business meaning.
- `enum_values`: list of `{ "value", "label", "description" }` objects.
- `reference`: lookup metadata for foreign keys.
- `validation_rules`: normalized validation rules.

Reference metadata:

```json
{
  "resource": "hr.department",
  "lookup_field": "name",
  "display_fields": ["id", "name"],
  "inline_values": [{ "id": 7, "name": "Sales" }],
  "complete": true,
  "max_records": 100,
  "hint": null
}
```

If `complete=false`, the consumer should query `resource` using `lookup_field`
before building the final operation payload.

## Operation

An operation describes one action the backend already supports.

Important fields:

- `scenario`: stable operation scenario, for example `query`, `create`, `update`.
- `method`: HTTP method or equivalent transport verb.
- `endpoint`: backend endpoint or operation address.
- `validation`: normalized validation rules for this scenario.
- `risk`: `low`, `medium`, `high` or `critical`.
- `requires_confirmation`: whether a human or policy gate must confirm.
- `permissions`: backend permissions associated with the operation.
- `annotations`: optional interoperability hints such as MCP annotations.

## Relations

Relation types should use protocol-level names where possible:

- `one-to-one`
- `one-to-many`
- `many-to-one`
- `many-to-many`

Framework names such as `hasMany` or `belongsToMany` may be retained in `meta`
for debugging, but portable consumers should use the normalized `type`.

## Versioning

Patch changes:

- Adding optional fields.
- Adding enum values to documented extensibility points.
- Clarifying descriptions.

Minor changes:

- Adding optional descriptors.
- Adding optional endpoint links.
- Adding new non-required capabilities.

Breaking changes:

- Removing fields.
- Renaming fields.
- Changing the meaning of an existing field.
- Making an optional field required.
- Changing filter syntax without publishing a compatibility descriptor.
