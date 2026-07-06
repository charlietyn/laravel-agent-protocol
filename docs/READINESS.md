# ADP Readiness

Every compiled resource receives a readiness score. The score tells downstream
tools whether a resource is safe enough for agent automation or should remain
documentation-only.

| Score | Status | Decision |
|---|---|---|
| 90-100 | `agent_ready` | Low-risk operations can be exposed for automation. |
| 70-89 | `partially_ready` | Allow queries; require confirmation for mutations. |
| 50-69 | `documentation_only` | Use for discovery and human documentation. |
| <50 | `not_ready` | Do not publish as an executable tool. |

The scorer checks for endpoint metadata, operations, fields, validation,
filters, security metadata, risk metadata and examples.

Example:

```json
{
  "readiness": {
    "score": 95,
    "status": "agent_ready",
    "decision": "can_expose_low_risk_operations_for_automation"
  }
}
```

Readiness is advisory metadata. The Laravel API remains responsible for
authorization and validation.
