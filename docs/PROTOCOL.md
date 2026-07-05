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
