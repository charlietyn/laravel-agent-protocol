# ADP Business Scope Control Plan

Este documento define el plan para implementar control de permisos y scope de negocio en `ronu/laravel-agent-protocol`.

El objetivo es resolver un caso muy común:

```text
Un usuario puede tener permiso para consultar una entidad,
pero solo debe ver los datos que pertenecen a su negocio, doctor, clínica, tenant, equipo o sucursal.
```

Ejemplo:

```text
El Dr. Pérez tiene permiso clients.client.view.
Pero solo puede consultar clientes asociados a doctor_id = 15.
```

---

## 1. Principio principal

El sistema debe separar tres responsabilidades:

```text
Permission
  Decide si el usuario puede pedir una operación.

Scope
  Decide qué datos específicos puede tocar ese usuario.

Execution
  Ejecuta la query real aplicando permisos, scope, policies y reglas de negocio.
```

Regla corta:

```text
Agent Guard valida la operación.
Business Scope Control limita los datos.
Laravel ejecuta con policies, scopes y servicios reales.
```

---

## 2. Por qué hace falta

Un permiso como este:

```text
clients.client.view
```

solo responde:

```text
¿Puede consultar clientes?
```

Pero no responde:

```text
¿Clientes de qué doctor?
¿Clientes de qué tenant?
¿Clientes de qué clínica?
¿Clientes propios o todos?
¿Clientes activos o archivados?
¿Clientes de otra sucursal?
```

Por eso no basta con permisos. Hace falta scope de datos.

---

## 3. Diferencia entre permiso y scope

| Concepto | Pregunta que responde | Ejemplo |
|---|---|---|
| Permiso | ¿Puede hacer esta operación? | `clients.client.view` |
| Scope | ¿Sobre qué datos puede hacerla? | `doctor_id = 15` |
| Policy | ¿Este registro concreto está permitido? | `ClientPolicy::view($user, $client)` |
| Query scope | ¿Cómo limitar la consulta? | `where('doctor_id', 15)` |

No se debe resolver todo con nombres de permisos.

Mal diseño:

```text
clients.client.view.doctor.15
clients.client.view.clinic.7
clients.client.view.own
clients.client.view.all
```

Mejor diseño:

```text
permission: clients.client.view
scope: doctor_id = 15, tenant_id = 7
```

---

## 4. Flujo completo recomendado

```text
User / internal service / HTTP token
  ↓
AgentContextResolver resuelve usuario real
  ↓
PermissionResolver obtiene permisos reales
  ↓
BusinessScopeResolver obtiene scopes reales
  ↓
Input Guard valida texto bruto
  ↓
LLM o adapter crea IntentPlan
  ↓
Agent Guard valida recurso, operación, campos, filtros, relaciones y permisos
  ↓
BusinessScopeEnforcer aplica filtros obligatorios
  ↓
Execution Adapter ejecuta servicio real
  ↓
Laravel Service / Query / Policy vuelve a aplicar scope
  ↓
Audit Trail registra decisiones, scope aplicado, tokens, lock y resultado
```

---

## 5. Regla de confianza

El prompt nunca debe decidir scope.

El LLM nunca debe inventar scope.

Un cliente externo nunca debe mandar permisos o scopes confiables.

El scope nace desde Laravel:

```text
usuario autenticado
roles
permisos
tenant
perfil de doctor
clínica
sucursal
equipo
relaciones de negocio
policies
base de datos
```

---

## 6. Escenario principal: clientes de un doctor

Usuario:

```text
id = 34
tenant_id = 7
doctor_id = 15
permissions = [clients.client.view]
```

Prompt:

```text
Muéstrame mis clientes activos
```

IntentPlan generado:

```json
{
  "resource": "clients.client",
  "operation": "query",
  "select": ["id", "name", "email", "phone", "status"],
  "filters": {
    "oper": {
      "and": ["status|=|active"]
    }
  }
}
```

El IntentPlan no trae `doctor_id`.

Eso es correcto.

Laravel debe inyectarlo:

```json
{
  "oper": {
    "and": [
      "status|=|active",
      "tenant_id|=|7",
      "doctor_id|=|15"
    ]
  }
}
```

---

## 7. Componentes propuestos

### 7.1 AgentContextResolver

Responsabilidad:

```text
Construir AgentContext desde un usuario real, request HTTP, token, job, command o servicio interno.
```

Ubicación propuesta:

```text
src/Runtime/Context/AgentContextResolver.php
```

Métodos sugeridos:

```php
interface AgentContextResolver
{
    public function fromAuthenticatedUser(mixed $user, array $attributes = []): AgentContext;

    public function fromRequest(Request $request): AgentContext;

    public function fromArray(array $data): AgentContext;
}
```

Reglas:

```text
Nunca confiar permisos enviados desde el prompt.
Nunca confiar scopes enviados por el cliente externo sin validarlos.
Siempre resolver permisos desde Laravel.
Siempre resolver scope desde reglas de negocio.
```

---

### 7.2 PermissionResolver

Responsabilidad:

```text
Extraer permisos reales del usuario.
```

Ubicación propuesta:

```text
src/Runtime/Permissions/PermissionResolver.php
```

Implementaciones sugeridas:

```text
NullPermissionResolver
SpatiePermissionResolver
CallbackPermissionResolver
```

Config:

```php
'permissions' => [
    'resolver' => env('AGENT_PROTOCOL_PERMISSION_RESOLVER', 'auto'),
    // auto | spatie | callback | null

    'callback' => null,
]
```

Ejemplo Spatie:

```php
$permissions = $user->getAllPermissions()
    ->pluck('name')
    ->values()
    ->all();
```

---

### 7.3 BusinessScopeResolver

Responsabilidad:

```text
Resolver restricciones de datos para un recurso ADP.
```

Ubicación propuesta:

```text
src/Runtime/Scope/BusinessScopeResolver.php
```

Contrato:

```php
interface BusinessScopeResolver
{
    public function resolve(string $resource, string $operation, AgentContext $context): BusinessScope;
}
```

Ejemplo de resultado:

```json
{
  "resource": "clients.client",
  "operation": "query",
  "filters": [
    {"field": "tenant_id", "operator": "=", "value": "7"},
    {"field": "doctor_id", "operator": "=", "value": "15"}
  ],
  "mode": "enforce",
  "reason": "Doctor users may only see clients assigned to themselves."
}
```

---

### 7.4 BusinessScope

DTO sugerido:

```text
src/Runtime/Scope/BusinessScope.php
```

Campos:

```text
resource
operation
filters
route_params
payload_constraints
allowed_ids
denied_ids
mode
reason
metadata
```

`mode` puede ser:

```text
enforce
  Agrega restricciones obligatorias.

deny
  Bloquea la operación por scope.

allow
  No agrega restricciones extra.

review
  Requiere revisión humana o confirmación.
```

---

### 7.5 BusinessScopeEnforcer

Responsabilidad:

```text
Aplicar el scope obligatorio al IntentPlan antes de ejecutar.
```

Ubicación propuesta:

```text
src/Runtime/Scope/BusinessScopeEnforcer.php
```

Ejemplo:

```php
$scopedPlan = app(BusinessScopeEnforcer::class)->apply(
    plan: $plan,
    scope: $scope,
);
```

Reglas:

```text
No debe eliminar filtros seguros del usuario.
Debe agregar filtros obligatorios con AND.
Debe impedir que el usuario contradiga scope obligatorio.
Debe marcar en meta qué scope fue aplicado.
Debe devolver una decisión auditable.
```

---

### 7.6 ScopeConflictDetector

Responsabilidad:

```text
Detectar cuando el IntentPlan intenta contradecir el scope obligatorio.
```

Ejemplo:

Scope obligatorio:

```text
doctor_id = 15
```

IntentPlan malicioso o incorrecto:

```text
doctor_id = 20
```

Resultado:

```json
{
  "allowed": false,
  "code": "ADP_SCOPE_CONFLICT",
  "message": "The requested filters conflict with mandatory business scope.",
  "details": {
    "field": "doctor_id",
    "required": "15",
    "requested": "20"
  }
}
```

---

### 7.7 ScopedExecutionAdapter

Responsabilidad:

```text
Ejecutar la operación real aplicando de nuevo el scope en Laravel.
```

El enforcer ayuda al IntentPlan, pero la query final también debe protegerse.

Regla:

```text
Nunca confiar solo en el IntentPlan ya scopeado.
La query real debe aplicar tenant, doctor, clinic o owner otra vez.
```

---

## 8. Configuración propuesta

```php
'business_scope' => [
    'enabled' => env('AGENT_PROTOCOL_BUSINESS_SCOPE_ENABLED', true),

    'default_mode' => env('AGENT_PROTOCOL_BUSINESS_SCOPE_DEFAULT_MODE', 'enforce'),
    // enforce | deny | allow

    'fail_closed' => env('AGENT_PROTOCOL_BUSINESS_SCOPE_FAIL_CLOSED', true),

    'audit' => [
        'enabled' => true,
        'log_applied_scope' => true,
        'log_scope_conflicts' => true,
    ],

    'resolvers' => [
        'clients.client' => App\Agent\Scopes\ClientBusinessScopeResolver::class,
        'medical.appointment' => App\Agent\Scopes\AppointmentBusinessScopeResolver::class,
        'sales.invoice' => App\Agent\Scopes\InvoiceBusinessScopeResolver::class,
    ],

    'global_scopes' => [
        'tenant' => [
            'enabled' => true,
            'attribute' => 'tenant_id',
            'field' => 'tenant_id',
        ],
    ],
]
```

---

## 9. Configuración por recurso

Ejemplo:

```php
'resources' => [
    'clients.client' => [
        'module' => 'clients',
        'model' => App\Models\Client::class,

        'operations' => [
            'query' => [
                'permissions' => ['clients.client.view'],
                'risk' => 'low',
            ],
            'show' => [
                'permissions' => ['clients.client.view'],
                'risk' => 'low',
            ],
            'update' => [
                'permissions' => ['clients.client.update'],
                'risk' => 'medium',
            ],
        ],

        'business_scope' => [
            'enabled' => true,
            'resolver' => App\Agent\Scopes\ClientBusinessScopeResolver::class,
            'mandatory_fields' => ['tenant_id', 'doctor_id'],
            'conflict_policy' => 'deny',
        ],
    ],
]
```

---

## 10. Ejemplo de resolver para clientes de doctor

```php
use Ronu\LaravelAgentProtocol\Runtime\Scope\BusinessScope;
use Ronu\LaravelAgentProtocol\Runtime\Scope\BusinessScopeResolver;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\AgentContext;

final class ClientBusinessScopeResolver implements BusinessScopeResolver
{
    public function resolve(string $resource, string $operation, AgentContext $context): BusinessScope
    {
        $tenantId = $context->tenantId;
        $doctorId = $context->attributes['doctor_id'] ?? null;
        $canViewAll = in_array('clients.client.view_all', $context->permissions, true);

        if ($tenantId === null) {
            return BusinessScope::deny(
                resource: $resource,
                operation: $operation,
                reason: 'Tenant is required to query clients.'
            );
        }

        if ($canViewAll) {
            return BusinessScope::enforce(
                resource: $resource,
                operation: $operation,
                filters: [
                    ['field' => 'tenant_id', 'operator' => '=', 'value' => $tenantId],
                ],
                reason: 'User can view all clients inside the current tenant.'
            );
        }

        if ($doctorId === null) {
            return BusinessScope::deny(
                resource: $resource,
                operation: $operation,
                reason: 'Doctor scope is required to query clients.'
            );
        }

        return BusinessScope::enforce(
            resource: $resource,
            operation: $operation,
            filters: [
                ['field' => 'tenant_id', 'operator' => '=', 'value' => $tenantId],
                ['field' => 'doctor_id', 'operator' => '=', 'value' => $doctorId],
            ],
            reason: 'Doctor users may only query their assigned clients.'
        );
    }
}
```

---

## 11. Escenarios que debe cubrir

### 11.1 Doctor consulta sus clientes

```text
Permiso: clients.client.view
Scope: tenant_id = 7 AND doctor_id = 15
Resultado: allowed + scoped
```

---

### 11.2 Doctor intenta consultar clientes de otro doctor

Prompt:

```text
Muéstrame los clientes del doctor 20
```

Scope obligatorio:

```text
doctor_id = 15
```

Resultado recomendado:

```text
ADP_SCOPE_CONFLICT
```

No conviene cambiar silenciosamente `doctor_id = 20` por `doctor_id = 15` si el usuario pidió explícitamente otro doctor. En este caso es mejor bloquear o responder de forma segura.

---

### 11.3 Admin de clínica consulta todos los clientes de su tenant

Permisos:

```text
clients.client.view
clients.client.view_all
```

Scope:

```text
tenant_id = 7
```

Resultado:

```text
Puede ver clientes del tenant, no de otros tenants.
```

---

### 11.4 Super admin consulta varios tenants

Permisos:

```text
clients.client.view
clients.client.view_all
system.tenants.cross_access
```

Scope:

```text
puede consultar tenant explícito si policy lo permite
```

Regla:

```text
Cross-tenant debe ser critical o high risk y auditable.
```

---

### 11.5 Usuario sin tenant

Si el recurso requiere tenant y `tenantId` es null:

```text
fail_closed = true
```

Resultado:

```text
ADP_SCOPE_MISSING_CONTEXT
```

---

### 11.6 Operación de update

Para actualizar un cliente:

```text
Permiso: clients.client.update
Scope: tenant_id = 7 AND doctor_id = 15
Policy: ClientPolicy::update($user, $client)
```

Regla:

```text
Primero validar operación.
Luego cargar registro con scope.
Luego policy por registro.
Luego actualizar.
```

---

### 11.7 Operación delete

Para delete:

```text
Permiso: clients.client.delete
Scope obligatorio
Risk high
Human confirmation
Execution lock
Audit event
Policy por registro
```

---

### 11.8 Relación sensible

Ejemplo:

```text
Cliente -> invoices
```

El usuario puede ver clientes, pero no facturas.

Regla:

```text
El scope debe poder limitar relaciones.
```

Resultado:

```text
clients.client query allowed
relation invoices denied si falta sales.invoice.view
```

---

### 11.9 Campos sensibles

El usuario puede ver cliente, pero no campos sensibles.

Regla:

```text
Field visibility sigue siendo responsabilidad de ADP metadata + Agent Guard.
```

Scope no reemplaza seguridad de campos.

---

### 11.10 Jobs internos sin request HTTP

Cuando no hay request:

```php
$user = User::findOrFail($userId);
$context = app(AgentContextResolver::class)->fromAuthenticatedUser($user);
```

No hace falta token.

Hace falta usuario real.

---

## 12. Orden correcto de validación

Para queries:

```text
1. Input Guard
2. IntentPlan generado
3. Agent Guard valida operación/permisos/campos/filtros
4. Business Scope Resolver
5. Scope Conflict Detector
6. Business Scope Enforcer
7. Query real con Laravel scopes
8. Audit Trail
```

Para mutations:

```text
1. Input Guard
2. IntentPlan generado
3. Agent Guard valida operación/permisos/campos/payload
4. Risk Guard valida confirmación
5. Business Scope Resolver
6. Scope Conflict Detector
7. Execution Lock
8. Cargar registro con scope
9. Laravel Policy por registro
10. Ejecutar mutation
11. Audit Trail
```

---

## 13. Nuevos códigos de error propuestos

```text
ADP_SCOPE_MISSING_CONTEXT
ADP_SCOPE_DENIED
ADP_SCOPE_CONFLICT
ADP_SCOPE_RESOLVER_NOT_FOUND
ADP_SCOPE_FIELD_NOT_PUBLISHED
ADP_SCOPE_RELATION_DENIED
ADP_SCOPE_UNSAFE_CROSS_TENANT
```

---

## 14. Audit Trail para scope

Eventos nuevos:

```text
scope_resolved
scope_applied
scope_denied
scope_conflict
scope_missing_context
scope_execution_verified
```

Campos sugeridos:

```text
trace_id
resource
operation
tenant_id
user_identifier
scope_mode
scope_reason
scope_filters_hash
scope_filters_preview
scope_conflict_field
scope_required_value
scope_requested_value
allowed
created_at
```

Regla de seguridad:

```text
No guardar valores sensibles completos si el scope contiene información privada.
Guardar hash + preview seguro.
```

---

## 15. Integración con tokens consumidos

El scope también debe quedar asociado al consumo de tokens.

Ejemplo:

```text
Usuario A consume 2,000 tokens para preguntar por clientes.
Scope aplicado: doctor_id = 15.
Resultado: 25 clientes.
```

Esto permite reportes como:

```text
Tokens por tenant.
Tokens por doctor.
Tokens por recurso.
Tokens por operación.
Tokens por scope.
Tokens consumidos en intent generation vs natural response.
```

---

## 16. Fases de implementación

### Fase 1 — Documentación y contratos

Crear:

```text
src/Runtime/Scope/BusinessScope.php
src/Runtime/Scope/BusinessScopeFilter.php
src/Runtime/Scope/BusinessScopeDecision.php
src/Runtime/Scope/BusinessScopeResolver.php
src/Runtime/Scope/NullBusinessScopeResolver.php
```

Tests:

```text
scope allow
scope enforce
scope deny
scope missing context
```

---

### Fase 2 — AgentContextResolver y PermissionResolver

Crear:

```text
src/Runtime/Context/AgentContextResolver.php
src/Runtime/Permissions/PermissionResolver.php
src/Runtime/Permissions/SpatiePermissionResolver.php
src/Runtime/Permissions/CallbackPermissionResolver.php
```

Objetivo:

```text
Poder construir AgentContext de forma consistente para HTTP, interno, jobs y commands.
```

---

### Fase 3 — BusinessScopeEnforcer

Crear:

```text
src/Runtime/Scope/BusinessScopeEnforcer.php
src/Runtime/Scope/ScopeConflictDetector.php
```

Objetivo:

```text
Agregar filtros obligatorios con AND.
Detectar contradicciones explícitas.
Bloquear cross-tenant accidental.
```

---

### Fase 4 — Configuración por recurso

Extender config:

```text
agent-protocol.business_scope
resources.*.business_scope
```

Objetivo:

```text
Cada proyecto puede definir resolvers por recurso.
```

---

### Fase 5 — Audit Trail integration

Registrar:

```text
scope_resolved
scope_applied
scope_denied
scope_conflict
```

Objetivo:

```text
Saber por qué un usuario vio o no vio ciertos datos.
```

---

### Fase 6 — Execution Adapter examples

Documentar patrones para:

```text
Eloquent query
rest-generic-class
custom service
repository pattern
jobs internos
n8n/http adapter
```

---

### Fase 7 — Policies y record-level checks

Agregar guía para:

```text
ClientPolicy::view
ClientPolicy::update
ClientPolicy::delete
```

Regla:

```text
Para show/update/delete, cargar el registro con scope y luego aplicar policy.
```

---

## 17. Ejemplo final completo

```php
$user = User::findOrFail($userId);

$context = app(AgentContextResolver::class)->fromAuthenticatedUser($user, [
    'doctor_id' => $user->doctor_id,
    'clinic_id' => $user->clinic_id,
]);

$input = app(InputTextGuard::class)->validate($prompt);

if (! $input->allowed) {
    return SafeAgentResponse::fromInputGuard($input);
}

$plan = app(IntentPlanFactory::class)->fromPrompt($input->normalizedInput);

$guard = app(ToolExecutionGuard::class)->authorize(
    $plan,
    app(MetadataRepositoryContract::class)->refresh(),
    $context,
);

if (! $guard->allowed) {
    return SafeAgentResponse::fromGuard($guard);
}

$scope = app(BusinessScopeResolverRegistry::class)->resolve(
    resource: $plan->resource,
    operation: $plan->operation,
    context: $context,
);

$scopeDecision = app(BusinessScopeEnforcer::class)->apply($plan, $scope);

if (! $scopeDecision->allowed) {
    return SafeAgentResponse::fromScope($scopeDecision);
}

return app(AgentExecutionAdapter::class)->execute(
    plan: $scopeDecision->plan,
    context: $context,
    scope: $scope,
);
```

---

## 18. Regla final

```text
Permisos controlan acciones.
Scopes controlan datos.
Policies controlan registros concretos.
Agent Guard valida el contrato.
Laravel sigue siendo la autoridad final.
```

Para el caso de clientes de doctor:

```text
El doctor puede pedir clientes porque tiene clients.client.view.
Solo recibe sus clientes porque el scope fuerza doctor_id.
No puede saltarse el scope desde el prompt.
No puede ver otro tenant.
No puede modificar registros fuera de su scope.
Todo queda auditado.
```
