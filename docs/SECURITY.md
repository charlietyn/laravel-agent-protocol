# Security

ADP describes capabilities; it does not grant permission to execute them.
Execution must still go through the real Laravel API, middleware, policies,
FormRequests and service layer.

## Field Visibility

Sensitive fields are redacted from resource field metadata by default. The
default sensitive list includes passwords, tokens, secrets and two-factor
recovery data.

Configuration:

```php
'security' => [
    'redact_sensitive_fields' => true,
    'expose_sensitive_fields' => false,
    'sensitive_fields' => ['password', 'api_token'],
    'hidden_fields' => [],
    'public_fields' => [],
],
```

Use `public_fields` when a resource must publish an explicit allowlist.

## Operation Risk

Operations are classified as `low`, `medium`, `high` or `critical`.

| Risk | Examples | Policy |
|---|---|---|
| low | query, show, metadata discovery | Auth and rate limits are usually enough. |
| medium | create, update, controlled export | Validate strictly and log. |
| high | bulk update, delete, restore, assign roles | Require preview or human confirmation. |
| critical | force delete, password changes, global permissions | Block by default or require double confirmation. |

The compiler marks `high` and `critical` operations with
`requires_confirmation=true`.

## Permissions And Context

Resource and operation descriptors can publish:

- `middleware`
- `guards`
- `permissions`
- `tenant_header`
- `locale_header`
- `tenant_aware`
- `locale_aware`

Route discovery also attempts to infer permissions from middleware such as
`can:*`, `permission:*` and `permissions:*`.

## Threat Model

ADP mitigates common agent risks by publishing a closed contract:

- prompt injection: backend validation and fixed capabilities still apply;
- tool misuse: risk and confirmation metadata guide adapters;
- data exfiltration: only allowed fields and relations are published;
- overfetching: filter limits and relation allowlists are discoverable;
- filter explosion: `max_depth` and `max_conditions` are published.
