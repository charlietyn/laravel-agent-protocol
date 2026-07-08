# ADP Agent Guard

ADP Agent Guard is the deterministic safety layer for agents, MCP adapters and n8n workflows that consume `ronu/laravel-agent-protocol` metadata.

It does not call an LLM, execute HTTP requests or change business data. Its job is to validate a proposed tool plan against the published ADP graph before any adapter calls the real Laravel API.

## Why it exists

LLMs can be prompt-hijacked, can invent fields, can choose destructive tools or can be asked for data outside the business domain. A prompt is not enough protection. Agent Guard turns ADP into a closed contract:

- if a resource is not published by ADP, it does not exist for the agent;
- if an operation is not published, it cannot be executed;
- if a field is hidden or sensitive, it cannot be selected, filtered or mutated;
- if a relation is not allowlisted, it cannot be included;
- if an operator is not allowed, the filter is rejected;
- if an operation is high or critical risk, confirmation or blocking policy applies;
- if the user asks for something outside the configured business domain, the request is rejected safely.

## Main classes

```text
Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext
Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan
Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentValidationResult
Ronu\LaravelAgentProtocol\Security\AgentGuard\BusinessDomainPolicy
Ronu\LaravelAgentProtocol\Security\AgentGuard\DomainGuard
Ronu\LaravelAgentProtocol\Security\AgentGuard\PromptInjectionSignalDetector
Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlanValidator
Ronu\LaravelAgentProtocol\Security\AgentGuard\OperationRiskGuard
Ronu\LaravelAgentProtocol\Security\AgentGuard\ToolExecutionGuard
Ronu\LaravelAgentProtocol\Security\AgentGuard\SafeRejectionResponder
Ronu\LaravelAgentProtocol\Security\AgentGuard\UntrustedContentSanitizer
```

## Configuration

```php
// config/agent-protocol.php

'agent_guard' => [
    'enabled' => true,
    'mode' => 'closed_world',

    'domain' => [
        'enabled' => true,
        'mode' => 'closed',
        'allowed_modules' => ['security', 'clients', 'medical', 'sales'],
        'blocked_resources' => ['system.config', 'security.internal_token'],
        'blocked_topics' => ['passwords', 'tokens', 'secrets', 'system prompts'],
    ],

    'prompt_injection' => [
        'enabled' => true,
        'strategy' => 'detect_and_block',
        'patterns' => [
            'ignore previous instructions',
            'reveal your system prompt',
            'bypass policy',
            'ignora las instrucciones anteriores',
        ],
    ],

    'risk' => [
        'confirmation_required_for' => ['high', 'critical'],
        'critical_default' => 'block',
        'block_without_confirmation' => true,
    ],
],
```

`AGENT_PROTOCOL_ALLOWED_MODULES` can be used to configure the comma-separated allowed module list from the environment.

## Secure execution flow

```text
User prompt
  -> LLM produces IntentPlan JSON
  -> ToolExecutionGuard validates the plan
  -> DomainGuard checks business domain
  -> IntentPlanValidator checks resource, operation, fields, filters and relations
  -> OperationRiskGuard checks confirmation and critical policy
  -> adapter calls the real Laravel API only if allowed
  -> Laravel middleware, policies, FormRequests and services still authorize and execute
```

## Intent plan example

The LLM or adapter should produce structured JSON, not free-form executable text:

```json
{
  "resource": "security.user",
  "operation": "query",
  "select": ["id", "name", "email", "status"],
  "filters": {
    "oper": {
      "and": ["status|=|active"]
    }
  },
  "relations": ["roles:id,name"]
}
```

Usage:

```php
use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\ToolExecutionGuard;

$plan = IntentPlan::fromArray($llmOutput);
$context = new AgentContext(
    userIdentifier: (string) auth()->id(),
    tenantId: request()->header('X-Tenant-Id'),
    locale: request()->header('Accept-Language'),
    source: 'n8n',
    channel: 'webhook',
    permissions: ['security.user.view'],
);

$result = app(ToolExecutionGuard::class)->authorize($plan, $graph, $context);

if (! $result->allowed) {
    return response()->json($result->toArray(), $result->status());
}

// Adapter may now call the real Laravel API.
```

## Rejection examples

### Out of domain

```json
{
  "ok": false,
  "code": "ADP_INTENT_OUT_OF_DOMAIN",
  "message": "The requested intent is outside the published ADP business domain.",
  "action": "blocked"
}
```

### Forbidden field

```json
{
  "ok": false,
  "code": "ADP_FORBIDDEN_FIELD",
  "message": "Field [password] is not visible, selectable, filterable or published by ADP.",
  "action": "blocked"
}
```

### Confirmation required

```json
{
  "ok": false,
  "code": "ADP_CONFIRMATION_REQUIRED",
  "message": "Operation [delete] is [high] risk and requires explicit human confirmation.",
  "action": "confirmation_required"
}
```

### Prompt hijacking signal

```json
{
  "ok": false,
  "code": "ADP_UNTRUSTED_INSTRUCTION_DETECTED",
  "message": "The request contains instructions that attempt to override the ADP execution policy.",
  "action": "blocked"
}
```

## n8n integration

Recommended node chain:

```text
Webhook / Chat
  -> ADP Discover Bundle
  -> LLM Resolve Intent
  -> ADP Validate Intent
  -> ADP Risk Gate
  -> Human Approval when required
  -> ADP Execute Operation
  -> ADP Format Response
  -> ADP Audit Log
```

The custom n8n node should call `ToolExecutionGuard` semantics before `HTTP Request`. The Laravel API remains the source of truth for permissions and execution.

## MCP integration

An MCP adapter should validate the model-selected tool arguments as an `IntentPlan` before calling the underlying API. MCP annotations such as `readOnlyHint`, `destructiveHint`, `idempotentHint` and `openWorldHint` should remain hints only; Agent Guard is the deterministic enforcement layer.

## Token cost

Agent Guard itself does not consume tokens. It is PHP validation over the compiled ADP graph.

Tokens are consumed when:

- the adapter sends ADP metadata to an LLM;
- the LLM produces an intent plan;
- the LLM formats the final human answer.

Recommended cost controls:

- use `/agent/bundle?mode=full` only on first discovery;
- cache bundles with ETag and Last-Modified;
- use `/agent/bundle?mode=slim` after references are cached;
- send only the relevant resource/operation metadata to the LLM;
- keep the LLM output as compact `IntentPlan` JSON;
- run all security validation deterministically in PHP.

## Production checklist

- Configure `agent_guard.domain.allowed_modules`.
- Keep `mode=closed_world` for MCP and n8n adapters.
- Keep high and critical operations confirmation-gated.
- Keep critical operations blocked unless a project-specific policy explicitly allows them.
- Run `php artisan agent:validate` in CI.
- Add adversarial tests for each custom resource and mutation.
- Wrap API response data with `UntrustedContentSanitizer` before sending it back to an LLM.
