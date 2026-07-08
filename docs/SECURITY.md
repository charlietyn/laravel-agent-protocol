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

## Agent Guard

`ADP Agent Guard` is the deterministic safety layer for agents, n8n workflows and
MCP adapters. It validates a model-generated `IntentPlan` against the compiled
ADP graph before any adapter calls the real API.

It protects against:

- prompt hijacking signals such as attempts to ignore ADP or reveal system prompts;
- requests outside the configured business domain;
- invented resources, operations, fields, relations or operators;
- sensitive or hidden fields being selected, filtered or mutated;
- destructive operations running without confirmation;
- critical operations running when policy says they must be blocked.

Agent Guard is configured under `agent_guard` in `config/agent-protocol.php` and
is available from the container as `ToolExecutionGuard`.

```php
$result = app(\Ronu\LaravelAgentProtocol\Security\AgentGuard\ToolExecutionGuard::class)
    ->authorize($intentPlan, $graph, $agentContext);

if (! $result->allowed) {
    return response()->json($result->toArray(), $result->status());
}
```

See [ADP Agent Guard](AGENT_GUARD.md) for configuration, examples, n8n flow and
MCP adapter guidance.

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

Agent Guard can validate published permission hints against an `AgentContext`,
but the real authorization decision must still happen in Laravel middleware,
policies and service code.

## Threat Model

ADP mitigates common agent risks by publishing a closed contract:

- prompt injection: backend validation and fixed capabilities still apply;
- prompt hijacking: Agent Guard rejects attempts to override execution policy;
- tool misuse: risk and confirmation metadata guide adapters and guards;
- data exfiltration: only allowed fields and relations are published;
- overfetching: filter limits and relation allowlists are discoverable;
- filter explosion: `max_depth` and `max_conditions` are published;
- out-of-domain requests: closed business-domain policy rejects unrelated tasks.

## Untrusted API Data

Data returned by the Laravel API must not be treated as new instructions for the
agent. When adapters send API results back to an LLM, wrap them as untrusted data:

```php
$wrapped = app(\Ronu\LaravelAgentProtocol\Security\AgentGuard\UntrustedContentSanitizer::class)
    ->wrap($apiResponse);
```

This makes the trust boundary explicit for n8n and MCP adapters.
