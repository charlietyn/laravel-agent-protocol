# Business Scope Control

Business Scope Control is the runtime layer that answers this question:

```text
The user can perform the operation, but which data is the user allowed to touch?
```

This document is written for juniors, but the design is production-oriented.

Important clarification:

```text
clients, doctors, clinics, invoices or appointments are only examples.
They are not part of the package architecture.
```

The package architecture is generic and works with any business domain:

```text
tenant
organization
company
branch
team
project
owner
department
region
clinic
warehouse
school
account
```

---

## 1. Mental model

There are three different controls:

| Layer | Question | Example |
|---|---|---|
| Permission | Can the user ask for this operation? | `resource.view` |
| Scope | Which records can the user access? | `tenant_id = 7` |
| Policy | Can the user access this concrete record? | `Policy::view($user, $model)` |

Do not mix them.

Bad design:

```text
resource.view.tenant.7
resource.view.owner.42
resource.view.team.9
```

Better design:

```text
permission: resource.view
scope: tenant_id = 7, owner_id = 42
```

---

## 2. Runtime flow

```text
Real user / request / job / internal service
  ↓
AgentContextResolver builds AgentContext
  ↓
PermissionResolver loads real permissions from Laravel
  ↓
InputTextGuard validates raw text
  ↓
LLM or adapter creates IntentPlan
  ↓
Agent Guard validates resource, operation, fields, filters and permissions
  ↓
BusinessScopeResolver resolves mandatory data restrictions
  ↓
BusinessScopeEnforcer applies those restrictions to the IntentPlan
  ↓
Execution Adapter runs the real service/query
  ↓
Laravel service applies scope again as defense in depth
```

The LLM does not decide scope.

The prompt does not decide scope.

The client does not send trusted scope.

Laravel decides scope from the real user and real business rules.

---

## 3. Components

### 3.1 PermissionResolver

Extracts permissions from the real user.

Included implementations:

```text
PermissionResolver
NullPermissionResolver
SpatiePermissionResolver
CallbackPermissionResolver
```

Default behavior:

```text
auto -> Spatie-compatible resolver
```

If the user model has `getAllPermissions()`, the package extracts permission names.

---

### 3.2 AgentContextResolver

Builds `AgentContext` from:

```text
authenticated user
HTTP request
job
command
internal service
array
```

Example:

```php
$context = app(AgentContextResolver::class)->fromAuthenticatedUser($user, [
    'source' => 'internal',
    'channel' => 'agent-runner',
    'owner_id' => $user->id,
    'organization_id' => $user->organization_id,
]);
```

The context contains:

```text
user_identifier
tenant_id
locale
source
channel
permissions
attributes
```

`attributes` is where a project can pass business values used for scope.

Examples:

```php
[
    'owner_id' => $user->id,
    'organization_id' => $user->organization_id,
    'branch_id' => $user->branch_id,
    'team_id' => $user->team_id,
]
```

---

### 3.3 BusinessScopeResolver

Resolves mandatory data restrictions for a resource and operation.

Example result:

```php
BusinessScope::enforce(
    resource: 'domain.resource',
    operation: 'query',
    filters: [
        new BusinessScopeFilter('tenant_id', '=', '7'),
        new BusinessScopeFilter('owner_id', '=', '42'),
    ],
    reason: 'User may only access records inside the current tenant and owner scope.',
);
```

---

### 3.4 BusinessScopeEnforcer

Applies scope filters to the IntentPlan using `AND`.

Original plan:

```php
[
    'oper' => [
        'and' => [
            'status|=|active',
        ],
    ],
]
```

Scope:

```text
tenant_id = 7
owner_id = 42
```

Scoped plan:

```php
[
    'oper' => [
        'and' => [
            'status|=|active',
            'tenant_id|=|7',
            'owner_id|=|42',
        ],
    ],
]
```

---

## 4. Scope conflict

A conflict happens when the user or LLM explicitly asks for data outside mandatory scope.

Mandatory scope:

```text
tenant_id = 7
```

Requested filter:

```text
tenant_id = 20
```

Result:

```text
ADP_SCOPE_CONFLICT
```

This is safer than silently changing `tenant_id = 20` to `tenant_id = 7` because the user explicitly asked for something outside scope.

---

## 5. Configuration

### 5.1 Permission resolver

```php
'permissions' => [
    'resolver' => env('AGENT_PROTOCOL_PERMISSION_RESOLVER', 'auto'),
    // auto | spatie | callback | null

    'callback' => null,
],
```

### 5.2 Runtime context

```php
'runtime_context' => [
    'tenant_header' => env('AGENT_PROTOCOL_TENANT_HEADER', 'X-Tenant-Id'),
    'locale_header' => env('AGENT_PROTOCOL_LOCALE_HEADER', 'Accept-Language'),
    'user_identifier_attribute' => 'id',
    'tenant_attribute' => 'tenant_id',
    'scope_attributes' => [],
],
```

`scope_attributes` can auto-copy values from the user model into `AgentContext->attributes`.

Example:

```php
'runtime_context' => [
    'scope_attributes' => [
        'organization_id' => 'organization_id',
        'branch_id' => 'branch_id',
        'team_id' => 'team_id',
    ],
],
```

### 5.3 Business scope

```php
'business_scope' => [
    'enabled' => true,
    'fail_closed' => true,
    'conflict_policy' => 'deny',

    'global_scopes' => [
        'tenant' => [
            'enabled' => true,
            'attribute' => 'tenant_id',
            'field' => 'tenant_id',
            'operator' => '=',
        ],
    ],

    'resources' => [
        'domain.resource' => [
            'required' => true,
            'filters' => [
                ['field' => 'owner_id', 'attribute' => 'owner_id'],
            ],
        ],
    ],

    'resolvers' => [
        'domain.resource' => App\Agent\Scopes\DomainResourceScopeResolver::class,
    ],
],
```

Use `resources` for simple config-driven scope.

Use `resolvers` when scope depends on complex business logic.

---

## 6. Simple internal example

```php
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\InputGuard\InputTextGuard;
use Ronu\LaravelAgentProtocol\Runtime\Context\AgentContextResolver;
use Ronu\LaravelAgentProtocol\Runtime\Scope\BusinessScopeEnforcer;
use Ronu\LaravelAgentProtocol\Runtime\Scope\BusinessScopeResolverRegistry;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\ToolExecutionGuard;

$context = app(AgentContextResolver::class)->fromAuthenticatedUser($user, [
    'source' => 'internal',
    'channel' => 'service',
    'owner_id' => $user->id,
]);

$input = app(InputTextGuard::class)->validate($prompt);

if (! $input->allowed) {
    return ['ok' => false, 'code' => $input->code()];
}

$plan = new IntentPlan(
    resource: 'domain.resource',
    operation: 'query',
    select: ['id', 'name', 'status'],
    filters: ['oper' => ['and' => ['status|=|active']]],
    naturalLanguageIntent: $input->normalizedInput,
);

$guard = app(ToolExecutionGuard::class)->authorize(
    $plan,
    app(MetadataRepositoryContract::class)->refresh(),
    $context,
);

if (! $guard->allowed) {
    return ['ok' => false, 'code' => $guard->code()];
}

$scope = app(BusinessScopeResolverRegistry::class)->resolve(
    resource: $plan->resource,
    operation: $plan->operation,
    context: $context,
);

$scopeDecision = app(BusinessScopeEnforcer::class)->apply($plan, $scope);

if (! $scopeDecision->allowed) {
    return ['ok' => false, 'code' => $scopeDecision->code];
}

$scopedPlan = $scopeDecision->plan;

// Now execute using your real Laravel service, repository or rest-generic-class adapter.
```

---

## 7. Defense in depth

Even after `BusinessScopeEnforcer` modifies the IntentPlan, the final query must still apply scope again.

Example:

```php
$query = Model::query();

if ($context->tenantId !== null) {
    $query->where('tenant_id', $context->tenantId);
}

$ownerId = $context->attributes['owner_id'] ?? null;
if ($ownerId !== null) {
    $query->where('owner_id', $ownerId);
}
```

Why apply scope twice?

```text
1. The scoped IntentPlan helps the agent and adapter stay honest.
2. The final Laravel query protects the database even if the adapter has a bug.
```

---

## 8. Scenarios

### User can access only own records

```text
Permission: resource.view
Scope: owner_id = current_user.id
Result: allowed + scoped
```

### User asks for another owner

```text
Mandatory scope: owner_id = 42
Requested filter: owner_id = 99
Result: ADP_SCOPE_CONFLICT
```

### Tenant user

```text
Permission: resource.view
Scope: tenant_id = current tenant
Result: can access only tenant data
```

### Cross-tenant user

Cross-tenant access should be explicit, high risk, audited and usually confirmed.

```text
Permission: resource.view_all_tenants
Risk: high or critical
Audit: required
```

### Update/delete

For mutations:

```text
1. Validate IntentPlan.
2. Resolve scope.
3. Acquire lock if needed.
4. Load record using scope.
5. Run Laravel policy on the concrete record.
6. Execute mutation.
7. Audit result.
```

---

## 9. Rules for juniors

```text
Never trust scope from the prompt.
Never trust permissions from the LLM.
Always build AgentContext from the real Laravel user.
Always apply mandatory scope with AND.
Always block explicit scope conflicts.
Always apply scope again in the final Laravel query.
Always use policies for show/update/delete on concrete records.
```

Final rule:

```text
Permissions control actions.
Scopes control data.
Policies control concrete records.
Laravel remains the final authority.
```
