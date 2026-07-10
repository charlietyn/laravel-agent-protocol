# Business Scope Security Notes

This document records security invariants for Business Scope Control.

The examples are generic. Terms such as tenant, owner, branch, team or organization are placeholders for any business domain.

---

## 1. Tenant context must be trusted

`AgentContextResolver::fromRequest()` must prefer trusted context over request headers.

Safe priority:

```text
explicit pre-validated attributes
  ↓
authenticated user tenant attribute
  ↓
trusted tenant header only if explicitly enabled
```

Default behavior:

```php
'runtime_context' => [
    'trust_tenant_header' => false,
]
```

Reason:

```text
A client-controlled X-Tenant-Id must not override the authenticated user's tenant.
```

Only enable `trust_tenant_header` when another middleware has already validated the header against the authenticated user.

---

## 2. Required scope must fail closed

If a resource requires several mandatory filters, all required filters must be resolved.

Unsafe behavior:

```text
required scope: tenant_id + owner_id + branch_id
resolved: tenant_id + owner_id
missing: branch_id
result: allowed
```

Safe behavior:

```text
result: ADP_SCOPE_MISSING_CONTEXT
```

Reason:

```text
A partially resolved scope can widen access by silently dropping one mandatory constraint.
```

---

## 3. Scope enforcement must preserve existing filters

When Business Scope Control appends mandatory filters, it must not remove filters already validated by Agent Guard.

Original plan:

```php
[
    'oper' => [
        'and' => [
            ['status' => ['active']],
        ],
    ],
]
```

Scope:

```text
tenant_id = tenant-7
```

Safe scoped plan:

```php
[
    'oper' => [
        'and' => [
            ['status' => ['active']],
            'tenant_id|=|tenant-7',
        ],
    ],
]
```

Reason:

```text
Dropping structured filters can return broader results than the validated IntentPlan requested.
```

---

## 4. Final execution must still enforce scope

The scoped IntentPlan is useful, but the final Laravel execution must apply the same scope again.

```text
BusinessScopeEnforcer protects the IntentPlan.
Laravel query scopes / policies protect the real data access.
```

Never rely only on the LLM, prompt, request payload, or scoped plan for final data isolation.
