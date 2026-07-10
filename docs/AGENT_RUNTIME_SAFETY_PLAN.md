# ADP Agent Runtime Safety Plan

Este documento define el plan para implementar una capa opcional de seguridad operacional alrededor de `ronu/laravel-agent-protocol`.

La idea es agregar tres capacidades:

```text
1. Input Guard
   Valida y limita el texto que entra antes de que se use con un LLM o se convierta en IntentPlan.

2. Audit Trail
   Registra eventos importantes del ciclo del agente: entrada, validación, tokens, decisiones, locks y ejecución.

3. Execution Locks
   Evita duplicados, reintentos peligrosos y ejecuciones concurrentes de operaciones sensibles.
```

La funcionalidad debe ser opcional, segura por defecto y compatible con SQL y MongoDB.

---

## 1. Principio principal

La arquitectura debe mantener esta separación:

```text
Input Guard
  protege el texto bruto que entra.

Agent Guard
  valida el IntentPlan contra ADP.

Audit Trail
  registra qué pasó.

Execution Locks
  evita ejecuciones duplicadas o concurrentes.

Laravel
  sigue siendo la autoridad final de autorización y ejecución.
```

No se debe mezclar auditoría con autorización.

No se debe permitir que el log cambie la decisión de seguridad.

No se debe permitir que un lock reemplace permisos, policies ni FormRequests.

---

## 2. Por qué hace falta

Cuando una API se conecta con LLMs, n8n, MCP o SDKs, pasan cosas nuevas:

```text
El usuario puede mandar textos enormes.
El usuario puede intentar prompt injection.
El agente puede generar un IntentPlan inválido.
Una operación puede ejecutarse dos veces por error.
Un workflow puede reintentar una mutación.
Una operación puede consumir muchos tokens.
Un rechazo puede no quedar auditado.
Una aprobación humana puede necesitar trazabilidad.
```

Esta capa permite responder preguntas como:

```text
Qué prompt entró?
Cuántos tokens consumió?
Qué modelo se usó?
Qué operación intentó ejecutar?
Fue permitido o bloqueado?
Por qué se bloqueó?
Quién lo pidió?
Qué tenant era?
Hubo lock?
Cuánto tardó?
Qué respuesta devolvió el backend?
```

---

## 3. Vista general del flujo

```text
User prompt
  ↓
InputTextGuard valida tamaño, formato y señales peligrosas
  ↓
AuditLogger registra input_received o input_rejected
  ↓
LLM produce IntentPlan
  ↓
AuditLogger registra llm_completed y token usage
  ↓
Agent Guard valida IntentPlan
  ↓
AuditLogger registra guard_allowed o guard_rejected
  ↓
ExecutionLockManager intenta lock si aplica
  ↓
AuditLogger registra lock_acquired o lock_conflict
  ↓
Adapter llama Laravel API si todo es válido
  ↓
AuditLogger registra execution_succeeded o execution_failed
  ↓
LLM puede redactar respuesta natural
  ↓
AuditLogger registra response_formatted y tokens finales si aplica
```

---

## 4. Input Guard

### 4.1 Objetivo

Validar el texto de entrada antes de enviarlo al LLM, antes de convertirlo en IntentPlan o antes de guardarlo en auditoría.

Debe proteger contra:

- prompts demasiado grandes;
- exceso de líneas;
- texto binario o caracteres de control;
- repeticiones enormes;
- prompt injection básico;
- posible filtrado accidental de secretos;
- coste excesivo de tokens;
- payloads anómalos.

---

### 4.2 Configuración propuesta

```php
'input_guard' => [
    'enabled' => env('AGENT_PROTOCOL_INPUT_GUARD_ENABLED', true),

    'mode' => env('AGENT_PROTOCOL_INPUT_GUARD_MODE', 'reject'),
    // reject | truncate | warn

    'limits' => [
        'max_chars' => (int) env('AGENT_PROTOCOL_INPUT_MAX_CHARS', 12000),
        'max_bytes' => (int) env('AGENT_PROTOCOL_INPUT_MAX_BYTES', 48000),
        'max_lines' => (int) env('AGENT_PROTOCOL_INPUT_MAX_LINES', 300),
        'max_repeated_char_run' => (int) env('AGENT_PROTOCOL_INPUT_MAX_REPEATED_CHAR_RUN', 120),
    ],

    'normalization' => [
        'trim' => true,
        'collapse_control_chars' => true,
        'normalize_line_endings' => true,
    ],

    'security' => [
        'detect_prompt_injection' => true,
        'detect_secrets' => true,
        'deny_binary_content' => true,
        'deny_control_chars' => true,
    ],

    'secret_patterns' => [
        'api_key',
        'secret',
        'private_key',
        'password=',
        'bearer ',
        'access_token',
        'refresh_token',
        '.env',
    ],
],
```

---

### 4.3 Clases propuestas

```text
src/InputGuard/InputTextGuard.php
src/InputGuard/InputTextPolicy.php
src/InputGuard/InputTextValidationResult.php
src/InputGuard/InputTextViolation.php
src/InputGuard/InputTextNormalizer.php
src/InputGuard/SensitiveTextDetector.php
```

---

### 4.4 Resultado esperado

```php
$result = app(InputTextGuard::class)->validate($prompt);

if (! $result->allowed) {
    // bloquear, auditar y responder de forma segura
}
```

Respuesta de ejemplo:

```json
{
  "allowed": false,
  "code": "ADP_INPUT_TOO_LARGE",
  "message": "The input text exceeds the configured maximum character limit.",
  "details": {
    "max_chars": 12000,
    "actual_chars": 27500
  }
}
```

---

## 5. Token Usage

### 5.1 Objetivo

Registrar cuántos tokens se consumieron por operación, por usuario, por tenant, por workflow, por modelo o por conversación.

Esto permite:

- controlar costes;
- detectar prompts demasiado grandes;
- medir eficiencia;
- saber qué integración consume más;
- crear reportes por tenant;
- auditar uso de LLMs.

---

### 5.2 Dónde se capturan los tokens

Los tokens pueden aparecer en diferentes momentos:

```text
1. Al generar el IntentPlan.
2. Al formatear la respuesta natural al usuario.
3. Al pedir contexto técnico adicional.
4. Al resumir resultados.
5. Al hacer una llamada secundaria al LLM para explicación.
```

La biblioteca no debe depender de un proveedor específico.

Debe aceptar token usage como metadata externa.

Ejemplo:

```php
$audit->recordTokenUsage([
    'trace_id' => $traceId,
    'provider' => 'openai',
    'model' => 'gpt-5.5-thinking',
    'phase' => 'intent_plan_generation',
    'input_tokens' => 2400,
    'output_tokens' => 380,
    'total_tokens' => 2780,
    'estimated_cost' => null,
    'currency' => null,
]);
```

---

### 5.3 Campos de token usage

Campos recomendados:

```text
id
trace_id
request_id
conversation_id
audit_event_id
tenant_id
user_identifier
source
channel
provider
model
phase
input_tokens
cached_input_tokens
output_tokens
reasoning_tokens
tool_tokens
total_tokens
estimated_cost
currency
raw_usage
created_at
updated_at
```

Explicación:

| Campo | Uso |
|---|---|
| `trace_id` | Agrupa toda la interacción. |
| `audit_event_id` | Permite asociar tokens con un evento concreto. |
| `provider` | OpenAI, Anthropic, local, etc. |
| `model` | Modelo usado. |
| `phase` | Momento del flujo: intent, response, summary, context. |
| `input_tokens` | Tokens de entrada. |
| `cached_input_tokens` | Tokens de entrada cacheados si el proveedor lo reporta. |
| `output_tokens` | Tokens de salida. |
| `reasoning_tokens` | Tokens de razonamiento si el proveedor lo reporta. |
| `tool_tokens` | Tokens usados en herramientas si aplica. |
| `total_tokens` | Total normalizado. |
| `estimated_cost` | Coste estimado si se calcula fuera. |
| `raw_usage` | Payload original del proveedor. |

---

### 5.4 Fases sugeridas para tokens

```text
input_validation
intent_plan_generation
project_context_query
agent_guard_explanation
api_result_summary
natural_language_response
tool_call_formatting
error_recovery
```

---

### 5.5 Normalizador de tokens

Como cada proveedor devuelve nombres diferentes, se recomienda crear:

```text
src/Audit/TokenUsage/TokenUsage.php
src/Audit/TokenUsage/TokenUsageNormalizer.php
src/Audit/TokenUsage/TokenUsageRecorder.php
```

Ejemplo:

```php
$usage = TokenUsageNormalizer::fromOpenAi($response->usage);
$logger->recordTokenUsage($usage->withTraceId($traceId));
```

También se debe permitir registro manual:

```php
$logger->recordTokenUsage(new TokenUsage(
    traceId: $traceId,
    provider: 'openai',
    model: 'gpt-5.5-thinking',
    phase: 'natural_language_response',
    inputTokens: 1200,
    outputTokens: 220,
));
```

---

## 6. Audit Trail

### 6.1 Objetivo

Registrar eventos importantes sin obligar al proyecto a usar SQL, MongoDB ni una tabla fija.

Debe soportar:

```text
null store
sql store
mongodb store
custom store
```

---

### 6.2 Configuración propuesta

```php
'audit' => [
    'enabled' => env('AGENT_PROTOCOL_AUDIT_ENABLED', false),
    'store' => env('AGENT_PROTOCOL_AUDIT_STORE', 'null'),
    // null | sql | mongodb | custom

    'fail_mode' => env('AGENT_PROTOCOL_AUDIT_FAIL_MODE', 'open'),
    // open | closed
    // open: si falla auditoría, la operación principal no se rompe.
    // closed: si falla auditoría, se bloquea el flujo.

    'store_raw_prompt' => env('AGENT_PROTOCOL_AUDIT_STORE_RAW_PROMPT', false),
    'encrypt_raw_prompt' => env('AGENT_PROTOCOL_AUDIT_ENCRYPT_RAW_PROMPT', true),
    'store_raw_response' => env('AGENT_PROTOCOL_AUDIT_STORE_RAW_RESPONSE', false),

    'prompt_preview_chars' => (int) env('AGENT_PROTOCOL_AUDIT_PROMPT_PREVIEW_CHARS', 500),
    'response_preview_chars' => (int) env('AGENT_PROTOCOL_AUDIT_RESPONSE_PREVIEW_CHARS', 500),

    'sql' => [
        'connection' => env('AGENT_PROTOCOL_AUDIT_SQL_CONNECTION', env('DB_CONNECTION')),
        'events_table' => env('AGENT_PROTOCOL_AUDIT_SQL_EVENTS_TABLE', 'agent_protocol_audit_events'),
        'token_usage_table' => env('AGENT_PROTOCOL_AUDIT_SQL_TOKEN_TABLE', 'agent_protocol_token_usage'),
        'locks_table' => env('AGENT_PROTOCOL_AUDIT_SQL_LOCKS_TABLE', 'agent_protocol_execution_locks'),
        'field_map' => [],
    ],

    'mongodb' => [
        'connection' => env('AGENT_PROTOCOL_AUDIT_MONGO_CONNECTION', 'mongodb'),
        'events_collection' => env('AGENT_PROTOCOL_AUDIT_MONGO_EVENTS_COLLECTION', 'agent_protocol_audit_events'),
        'token_usage_collection' => env('AGENT_PROTOCOL_AUDIT_MONGO_TOKEN_COLLECTION', 'agent_protocol_token_usage'),
        'locks_collection' => env('AGENT_PROTOCOL_AUDIT_MONGO_LOCKS_COLLECTION', 'agent_protocol_execution_locks'),
    ],
],
```

---

### 6.3 Eventos recomendados

```text
input_received
input_rejected
input_accepted
llm_started
llm_completed
llm_failed
token_usage_recorded
intent_plan_created
guard_allowed
guard_rejected
confirmation_required
confirmation_approved
confirmation_denied
lock_acquired
lock_conflict
lock_released
execution_started
execution_succeeded
execution_failed
response_formatted
project_context_used
```

---

### 6.4 Campos principales del audit event

```text
id
trace_id
request_id
conversation_id
parent_id
event_type
event_status
source
channel
tenant_id
user_identifier
session_id
correlation_id
resource
operation
risk
requires_confirmation
confirmed
allowed
rejection_code
rejection_message
policy_action
lock_key
lock_status
provider
model
prompt_hash
prompt_preview
prompt_length_chars
prompt_length_bytes
raw_prompt
response_hash
response_preview
response_length_chars
raw_response
intent_plan_hash
intent_plan_preview
http_method
endpoint
response_status
duration_ms
error_code
error_message
metadata
created_at
updated_at
```

---

## 7. SQL migrations

La biblioteca debe publicar migrations opcionales con un tag:

```bash
php artisan vendor:publish --tag=agent-protocol-audit-migrations
```

O permitir instalarlas con un comando futuro:

```bash
php artisan agent:audit:install
```

---

### 7.1 Tabla `agent_protocol_audit_events`

Migration propuesta:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('agent-protocol.audit.sql.connection'))->create(
            config('agent-protocol.audit.sql.events_table', 'agent_protocol_audit_events'),
            function (Blueprint $table): void {
                $table->id();

                $table->uuid('trace_id')->index();
                $table->uuid('request_id')->nullable()->index();
                $table->uuid('conversation_id')->nullable()->index();
                $table->uuid('parent_id')->nullable()->index();

                $table->string('event_type', 80)->index();
                $table->string('event_status', 40)->default('recorded')->index();

                $table->string('source', 80)->nullable()->index();
                $table->string('channel', 80)->nullable()->index();
                $table->string('tenant_id', 120)->nullable()->index();
                $table->string('user_identifier', 190)->nullable()->index();
                $table->string('session_id', 190)->nullable()->index();
                $table->string('correlation_id', 190)->nullable()->index();

                $table->string('resource', 190)->nullable()->index();
                $table->string('operation', 120)->nullable()->index();
                $table->string('risk', 40)->nullable()->index();
                $table->boolean('requires_confirmation')->nullable();
                $table->boolean('confirmed')->nullable();
                $table->boolean('allowed')->nullable()->index();

                $table->string('rejection_code', 120)->nullable()->index();
                $table->text('rejection_message')->nullable();
                $table->string('policy_action', 80)->nullable()->index();

                $table->string('lock_key', 190)->nullable()->index();
                $table->string('lock_status', 60)->nullable()->index();

                $table->string('provider', 80)->nullable()->index();
                $table->string('model', 120)->nullable()->index();

                $table->string('prompt_hash', 128)->nullable()->index();
                $table->text('prompt_preview')->nullable();
                $table->unsignedInteger('prompt_length_chars')->nullable();
                $table->unsignedInteger('prompt_length_bytes')->nullable();
                $table->longText('raw_prompt')->nullable();

                $table->string('response_hash', 128)->nullable()->index();
                $table->text('response_preview')->nullable();
                $table->unsignedInteger('response_length_chars')->nullable();
                $table->longText('raw_response')->nullable();

                $table->string('intent_plan_hash', 128)->nullable()->index();
                $table->json('intent_plan_preview')->nullable();

                $table->string('http_method', 20)->nullable();
                $table->string('endpoint', 500)->nullable();
                $table->unsignedSmallInteger('response_status')->nullable()->index();
                $table->unsignedInteger('duration_ms')->nullable();

                $table->string('error_code', 120)->nullable()->index();
                $table->text('error_message')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['resource', 'operation', 'created_at']);
                $table->index(['event_type', 'created_at']);
            }
        );
    }

    public function down(): void
    {
        Schema::connection(config('agent-protocol.audit.sql.connection'))->dropIfExists(
            config('agent-protocol.audit.sql.events_table', 'agent_protocol_audit_events')
        );
    }
};
```

Notas:

```text
raw_prompt y raw_response deben venir null por defecto.
Si se guardan, deben poder cifrarse desde la capa AuditStore.
metadata debe guardar detalles variables sin romper columnas.
```

---

### 7.2 Tabla `agent_protocol_token_usage`

Migration propuesta:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('agent-protocol.audit.sql.connection'))->create(
            config('agent-protocol.audit.sql.token_usage_table', 'agent_protocol_token_usage'),
            function (Blueprint $table): void {
                $table->id();

                $table->uuid('trace_id')->index();
                $table->uuid('request_id')->nullable()->index();
                $table->uuid('conversation_id')->nullable()->index();
                $table->foreignId('audit_event_id')->nullable()->index();

                $table->string('tenant_id', 120)->nullable()->index();
                $table->string('user_identifier', 190)->nullable()->index();
                $table->string('source', 80)->nullable()->index();
                $table->string('channel', 80)->nullable()->index();

                $table->string('provider', 80)->nullable()->index();
                $table->string('model', 120)->nullable()->index();
                $table->string('phase', 80)->index();

                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('cached_input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('reasoning_tokens')->default(0);
                $table->unsignedInteger('tool_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0)->index();

                $table->decimal('estimated_cost', 12, 6)->nullable();
                $table->string('currency', 10)->nullable();

                $table->json('raw_usage')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['provider', 'model', 'created_at']);
                $table->index(['phase', 'created_at']);
            }
        );
    }

    public function down(): void
    {
        Schema::connection(config('agent-protocol.audit.sql.connection'))->dropIfExists(
            config('agent-protocol.audit.sql.token_usage_table', 'agent_protocol_token_usage')
        );
    }
};
```

Nota:

```text
No se debe forzar foreign key real hacia audit_events porque el usuario puede cambiar conexión, tabla o store.
Se guarda audit_event_id como referencia lógica opcional.
```

---

### 7.3 Tabla `agent_protocol_execution_locks`

Aunque la primera implementación puede usar `Cache::lock`, esta tabla sirve para locks persistentes o para auditar locks.

Migration propuesta:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('agent-protocol.audit.sql.connection'))->create(
            config('agent-protocol.audit.sql.locks_table', 'agent_protocol_execution_locks'),
            function (Blueprint $table): void {
                $table->id();

                $table->string('lock_key', 190)->unique();
                $table->uuid('trace_id')->nullable()->index();
                $table->uuid('request_id')->nullable()->index();

                $table->string('tenant_id', 120)->nullable()->index();
                $table->string('user_identifier', 190)->nullable()->index();
                $table->string('resource', 190)->nullable()->index();
                $table->string('operation', 120)->nullable()->index();

                $table->string('status', 60)->default('acquired')->index();
                $table->string('owner', 190)->nullable()->index();

                $table->timestamp('locked_at')->nullable()->index();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();

                $table->string('payload_hash', 128)->nullable()->index();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'resource', 'operation']);
                $table->index(['status', 'expires_at']);
            }
        );
    }

    public function down(): void
    {
        Schema::connection(config('agent-protocol.audit.sql.connection'))->dropIfExists(
            config('agent-protocol.audit.sql.locks_table', 'agent_protocol_execution_locks')
        );
    }
};
```

---

## 8. SQL field mapping

Como el usuario puede querer una tabla existente, SQL debe permitir mapear campos.

Ejemplo:

```php
'audit' => [
    'sql' => [
        'connection' => 'mysql_audit',
        'events_table' => 'ai_agent_logs',
        'field_map' => [
            'trace_id' => 'trace_uuid',
            'event_type' => 'type',
            'event_status' => 'status',
            'tenant_id' => 'tenant',
            'user_identifier' => 'user_id',
            'prompt_hash' => 'prompt_sha256',
            'prompt_preview' => 'prompt_excerpt',
            'resource' => 'adp_resource',
            'operation' => 'adp_operation',
            'allowed' => 'allowed',
            'metadata' => 'extra',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ],
    ],
],
```

Regla:

```text
Si field_map está vacío, usar columnas estándar.
Si field_map existe, insertar solo campos mapeados.
metadata debe poder guardar campos no mapeados si existe columna metadata.
```

---

## 9. MongoDB store

MongoDB debe insertar documentos completos, sin necesidad de field_map.

Ejemplo de documento:

```json
{
  "trace_id": "0190f2e4-...",
  "event_type": "guard_rejected",
  "event_status": "blocked",
  "tenant_id": "clinic_7",
  "user_identifier": "42",
  "source": "n8n",
  "channel": "webhook",
  "prompt": {
    "hash": "sha256...",
    "preview": "Muéstrame usuarios activos...",
    "length_chars": 28,
    "stored_raw": false
  },
  "intent_plan": {
    "resource": "security.user",
    "operation": "query",
    "hash": "sha256..."
  },
  "guard": {
    "allowed": false,
    "code": "ADP_FORBIDDEN_FIELD",
    "risk": "low"
  },
  "tokens": {
    "provider": "openai",
    "model": "gpt-5.5-thinking",
    "phase": "intent_plan_generation",
    "input_tokens": 2400,
    "output_tokens": 380,
    "total_tokens": 2780
  },
  "lock": {
    "key": "agent:...",
    "status": "released",
    "expires_at": "2026-07-10T12:00:00Z"
  },
  "metadata": {},
  "created_at": "2026-07-10T12:00:00Z"
}
```

MongoDB debe ser opcional.

No se debe agregar dependencia obligatoria en Composer.

El store Mongo debe validar que la conexión exista y que el driver esté instalado.

---

## 10. Execution Locks

### 10.1 Objetivo

Evitar que una mutación se ejecute dos veces por error.

Ejemplos:

```text
El usuario confirma dos veces.
Un workflow n8n reintenta una petición.
Un MCP adapter vuelve a llamar la misma tool.
Una operación bulk_update tarda y llega otra igual.
```

---

### 10.2 Configuración propuesta

```php
'locks' => [
    'enabled' => env('AGENT_PROTOCOL_LOCKS_ENABLED', false),
    'driver' => env('AGENT_PROTOCOL_LOCK_DRIVER', 'cache'),
    // cache | sql | mongodb

    'cache_store' => env('AGENT_PROTOCOL_LOCK_CACHE_STORE', env('CACHE_STORE')),
    'ttl' => (int) env('AGENT_PROTOCOL_LOCK_TTL', 60),

    'apply_to_risk' => ['medium', 'high', 'critical'],
    'apply_to_operations' => [
        'create',
        'update',
        'delete',
        'bulk_update',
        'restore',
        'force_delete',
    ],

    'key_strategy' => 'tenant_user_resource_operation_payload',
    'fail_on_lock_conflict' => true,
],
```

---

### 10.3 Lock key

Lock key recomendado:

```text
tenant_id
user_identifier
resource
operation
route_params_hash
payload_hash
confirmation_id
```

Ejemplo:

```php
$lockKey = hash('sha256', implode('|', [
    $tenantId,
    $userId,
    $plan->resource,
    $plan->operation,
    hash('sha256', json_encode($plan->routeParams)),
    hash('sha256', json_encode($plan->payload)),
    $confirmationId,
]));
```

---

## 11. Clases propuestas

```text
src/InputGuard/InputTextGuard.php
src/InputGuard/InputTextPolicy.php
src/InputGuard/InputTextValidationResult.php
src/InputGuard/InputTextViolation.php
src/InputGuard/InputTextNormalizer.php
src/InputGuard/SensitiveTextDetector.php

src/Audit/AgentAuditEvent.php
src/Audit/AgentAuditLogger.php
src/Audit/AuditStore.php
src/Audit/NullAuditStore.php
src/Audit/SqlAuditStore.php
src/Audit/MongoAuditStore.php
src/Audit/AuditFieldMapper.php

src/Audit/TokenUsage/TokenUsage.php
src/Audit/TokenUsage/TokenUsageNormalizer.php
src/Audit/TokenUsage/TokenUsageRecorder.php

src/Locks/AgentExecutionLockManager.php
src/Locks/AgentLockKeyFactory.php
src/Locks/AgentLockResult.php
```

---

## 12. Implementación por fases

### Fase 1 — Input Guard

Tareas:

- crear DTOs de validación;
- validar caracteres, bytes, líneas y repetición;
- detectar texto binario/control chars;
- reutilizar patrones de prompt injection;
- detectar secretos básicos;
- agregar config;
- agregar tests;
- agregar docs.

Criterio de aceptación:

```text
Un prompt demasiado grande se bloquea.
Un prompt con secretos genera warning o bloqueo según config.
El modo reject/truncate/warn funciona.
Nada se ejecuta si input_guard bloquea.
```

---

### Fase 2 — Audit Trail base

Tareas:

- crear `AgentAuditEvent`;
- crear `AgentAuditLogger`;
- crear `AuditStore`;
- crear `NullAuditStore`;
- crear `SqlAuditStore`;
- crear field mapper SQL;
- crear migrations publicables;
- registrar eventos principales;
- agregar tests.

Criterio de aceptación:

```text
Si audit.enabled=false no cambia nada.
Si store=null no falla nada.
Si store=sql inserta eventos.
Si field_map existe respeta columnas personalizadas.
```

---

### Fase 3 — Token Usage

Tareas:

- crear DTO `TokenUsage`;
- crear recorder;
- crear normalizador para payloads comunes;
- crear tabla SQL `agent_protocol_token_usage`;
- soportar raw_usage;
- registrar tokens por fase;
- documentar cómo pasar tokens desde OpenAI/Anthropic/local.

Criterio de aceptación:

```text
Se pueden registrar input/output/total tokens.
Se puede asociar uso a trace_id y audit_event_id.
Se puede consultar consumo por tenant, usuario, provider, model y phase.
```

---

### Fase 4 — MongoDB Audit Store

Tareas:

- crear `MongoAuditStore`;
- validar conexión;
- insertar documento completo;
- soportar token usage como colección separada o embebida;
- documentar índices recomendados.

Criterio de aceptación:

```text
Mongo no es dependencia obligatoria.
Si driver no existe, falla de forma controlada.
El documento se inserta completo.
```

---

### Fase 5 — Execution Locks

Tareas:

- crear lock key factory;
- crear lock manager;
- usar `Cache::lock` como driver inicial;
- registrar lock_acquired/lock_conflict/lock_released;
- soportar TTL;
- aplicar solo a operaciones/riesgos configurados.

Criterio de aceptación:

```text
Dos mutaciones iguales no se ejecutan en paralelo.
Las queries simples no se bloquean por defecto.
El conflicto de lock queda auditado.
```

---

### Fase 6 — Integración completa

Tareas:

- hooks antes/después de `ToolExecutionGuard`;
- helpers para n8n/MCP/SDK;
- documentación end-to-end;
- ejemplos de configuración SQL y Mongo;
- tests integrados.

Criterio de aceptación:

```text
Se puede reconstruir una interacción completa desde trace_id.
Se sabe qué prompt entró, qué tokens consumió, qué plan se generó, qué decidió Agent Guard y qué ejecutó Laravel.
```

---

## 13. Índices recomendados

### SQL audit events

```text
trace_id
tenant_id + created_at
user_identifier + created_at
event_type + created_at
resource + operation + created_at
allowed + created_at
rejection_code + created_at
```

### SQL token usage

```text
trace_id
provider + model + created_at
phase + created_at
tenant_id + created_at
total_tokens
```

### SQL locks

```text
lock_key unique
status + expires_at
tenant_id + resource + operation
```

### MongoDB indexes sugeridos

```js
db.agent_protocol_audit_events.createIndex({ trace_id: 1 })
db.agent_protocol_audit_events.createIndex({ tenant_id: 1, created_at: -1 })
db.agent_protocol_audit_events.createIndex({ event_type: 1, created_at: -1 })
db.agent_protocol_audit_events.createIndex({ resource: 1, operation: 1, created_at: -1 })

db.agent_protocol_token_usage.createIndex({ trace_id: 1 })
db.agent_protocol_token_usage.createIndex({ provider: 1, model: 1, created_at: -1 })
db.agent_protocol_token_usage.createIndex({ tenant_id: 1, created_at: -1 })

db.agent_protocol_execution_locks.createIndex({ lock_key: 1 }, { unique: true })
db.agent_protocol_execution_locks.createIndex({ status: 1, expires_at: 1 })
```

---

## 14. Reglas de privacidad

Por defecto:

```text
No guardar raw_prompt.
No guardar raw_response.
Guardar prompt_hash.
Guardar prompt_preview redacted.
Guardar longitudes.
Guardar tokens.
Guardar metadata técnica.
```

Si el proyecto decide guardar raw prompt:

```text
Debe ser explícito.
Debe poder cifrarse.
Debe documentarse.
Debe respetar política de privacidad del proyecto.
```

---

## 15. Resumen final

La funcionalidad propuesta se debe implementar como:

```text
ADP Agent Runtime Safety
  -> Input Guard
  -> Audit Trail
  -> Token Usage Recorder
  -> Execution Locks
```

Esto hará que la biblioteca sea más fuerte para producción, n8n, MCP y SDKs, porque permitirá controlar entrada, auditar decisiones, medir tokens y evitar ejecuciones duplicadas sin debilitar el contrato ADP ni la autorización Laravel.
