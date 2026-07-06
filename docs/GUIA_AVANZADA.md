# Guia Avanzada De Uso E Integracion

Esta guia documenta los cambios principales incorporados al paquete y explica
como usarlos en una API Laravel basada en `ronu/rest-generic-class`. El enfoque
es practico: que publicar, como se compila, como lo consume un agente, como se
cachea y como se integra con MCP, n8n u otros consumidores.

## 1. Vision Arquitectonica

`ronu/laravel-agent-protocol` es una libreria metadata-only. No ejecuta queries,
no modifica registros y no reemplaza la seguridad del backend. Su funcion es
compilar lo que la API ya sabe hacer y publicarlo como un grafo ADP
(`AgentMetadataGraph`) consumible por agentes LLM, MCP adapters, n8n nodes,
SDKs y documentacion generada.

La responsabilidad queda dividida asi:

| Capa | Responsabilidad |
|---|---|
| `ronu/rest-generic-class` | Ejecuta CRUD, filtros, relaciones, validaciones y permisos. |
| `laravel-agent-protocol` | Describe recursos, campos, operaciones, riesgos y reglas. |
| LLM | Infiere la intencion del usuario usando metadata publicada. |
| n8n | Actua como puente configurable, cachea metadata y llama la API. |
| MCP adapter | Expone resources/tools derivados de ADP. |
| Backend Laravel | Autoriza, valida, aplica tenant scopes y ejecuta negocio. |

La decision central es no mantener un diccionario manual de sinonimos como
fuente principal. ADP publica estructura semantica: labels, descripciones,
enums, relaciones y tablas de referencia. El LLM usa eso para resolver lenguaje
natural a operaciones concretas.

## 2. Metadata Semantica En Campos

`FieldDescriptor` ahora soporta metadata adicional:

- `label`: nombre humano breve.
- `description`: significado de negocio del campo.
- `enum_values`: valores permitidos con label y descripcion opcional.
- `reference`: metadata para foreign keys y tablas de referencia.
- `examples`: ejemplos de uso del campo.
- `meta`: metadata extensible para consumidores propios.

### 2.1 Configuracion Recomendada

```php
'resources' => [
    'security.user' => [
        'module' => 'security',
        'model' => App\Models\User::class,
        'request' => App\Http\Requests\UserRequest::class,
        'endpoint' => '/api/security/users',
        'description' => 'Users managed by the security module.',
        'fields' => [
            'status' => [
                'label' => 'User status',
                'description' => 'Lifecycle state used to filter active, inactive or suspended users.',
                'type' => 'enum',
                'enum_values' => [
                    [
                        'value' => 'active',
                        'label' => 'Active',
                        'description' => 'The user can access the system.',
                    ],
                    [
                        'value' => 'inactive',
                        'label' => 'Inactive',
                        'description' => 'The user exists but cannot access the system.',
                    ],
                    [
                        'value' => 'suspended',
                        'label' => 'Suspended',
                        'description' => 'The user was blocked by policy or administration.',
                    ],
                ],
                'examples' => [
                    ['prompt' => 'usuarios activos', 'value' => 'active'],
                    ['prompt' => 'usuarios suspendidos', 'value' => 'suspended'],
                ],
            ],
        ],
    ],
],
```

### 2.2 Salida ADP Esperada

```json
{
  "name": "status",
  "type": "enum",
  "label": "User status",
  "description": "Lifecycle state used to filter active, inactive or suspended users.",
  "enum_values": [
    {
      "value": "active",
      "label": "Active",
      "description": "The user can access the system."
    }
  ],
  "filterable": true,
  "selectable": true
}
```

### 2.3 Inferencia Del LLM

Cuando un usuario dice:

```text
Muestrame usuarios suspendidos
```

El consumidor carga ADP, detecta el recurso `security.user`, ve el campo
`status`, lee sus `enum_values`, y puede construir una consulta como:

```text
GET /api/security/users?oper=status|=|suspended
```

El backend sigue siendo responsable de validar si el token tiene permisos y si
el filtro es permitido.

## 3. Enriquecimiento Semantico Automatico

`SemanticMetadataPass` se ejecuta despues de descubrir o configurar recursos.
Su trabajo es enriquecer campos ya compilados con informacion semantica.

Fuentes que usa:

- `agent-protocol.resources.*.fields`
- `agent-protocol.reference_tables`
- reglas de validacion `in:...` detectadas por `ModelInspector`
- nombres de campos, para generar labels basicos como `department_id` ->
  `Department Id`

### 3.1 Que Hace El Pass

1. Recorre todos los recursos compilados.
2. Busca definiciones semanticas del recurso en config.
3. Enriquecie campos existentes sin reemplazar el contrato base.
4. Detecta foreign keys configuradas o inferidas.
5. Agrega `reference` metadata cuando un campo apunta a una tabla de referencia.
6. Recalcula readiness del recurso despues del enriquecimiento.

### 3.2 Regla De Compatibilidad

El pass no elimina campos ni operaciones. Solo agrega metadata opcional. Esto
mantiene compatibilidad con consumidores que ya usan el contrato anterior.

## 4. Tablas De Referencia Inline

Las tablas de referencia permiten resolver expresiones humanas a ids tecnicos.
Ejemplos comunes:

- departamentos
- roles
- categorias
- paises
- estados de factura
- tipos de documento
- centros de costo

### 4.1 Configuracion

```php
'reference_tables' => [
    'departments' => [
        'model' => App\Models\Department::class,
        'resource' => 'hr.department',
        'fields' => ['id', 'name'],
        'lookup_field' => 'name',
        'display_fields' => ['id', 'name'],
        'foreign_keys' => ['department_id'],
        'max_records' => 100,
        'cache_ttl' => 3600,
        'enabled' => true,
    ],
],
```

Tambien se pueden declarar valores manuales:

```php
'reference_tables' => [
    'departments' => [
        'resource' => 'hr.department',
        'fields' => ['id', 'name'],
        'lookup_field' => 'name',
        'foreign_keys' => ['department_id'],
        'max_records' => 100,
        'values' => [
            ['id' => 1, 'name' => 'Sales'],
            ['id' => 2, 'name' => 'Operations'],
        ],
    ],
],
```

### 4.2 Salida Cuando La Tabla Es Pequena

Si la cantidad de registros no excede `max_records`, ADP publica valores inline:

```json
{
  "name": "department_id",
  "type": "foreign_key",
  "reference": {
    "key": "departments",
    "resource": "hr.department",
    "lookup_field": "name",
    "display_fields": ["id", "name"],
    "inline_values": [
      { "id": 1, "name": "Sales" },
      { "id": 2, "name": "Operations" }
    ],
    "complete": true,
    "max_records": 100,
    "hint": null
  }
}
```

Con esto, el LLM puede resolver "usuarios de Sales" a
`department_id|=|1` sin hacer otra llamada.

### 4.3 Salida Cuando La Tabla Es Grande

Si la tabla excede `max_records`, ADP no publica los registros:

```json
{
  "name": "product_category_id",
  "type": "foreign_key",
  "reference": {
    "resource": "catalog.category",
    "lookup_field": "name",
    "inline_values": [],
    "complete": false,
    "max_records": 100,
    "hint": "Query catalog.category first to resolve by name."
  }
}
```

El consumidor debe consultar primero el recurso referenciado y luego ejecutar la
operacion final. Esto evita meter catalogos grandes dentro del contexto del LLM.

### 4.4 Criterio Senior Para `max_records`

Valores recomendados:

| Tipo de tabla | `max_records` sugerido |
|---|---:|
| estados fijos | 20 |
| roles/perfiles | 50 |
| departamentos | 100 |
| paises/provincias | 250 |
| productos/clientes | no inline |

No publiques inline tablas transaccionales o con datos sensibles.

## 5. Cache Dedicada `compiled_file`

La cache anterior usaba el cache store de Laravel. Ahora existe un modo
`compiled_file` para guardar metadata ADP como JSON compilado en una ruta
dedicada.

### 5.1 Configuracion

```php
'cache' => [
    'enabled' => true,
    'driver' => 'compiled_file',
    'store' => env('CACHE_STORE'),
    'key' => 'agent-protocol:metadata:v1',
    'ttl' => 3600,
    'path' => base_path('bootstrap/cache/adp'),
    'compiled_filename' => 'metadata.json',
    'etag' => true,
    'last_modified' => true,
    'vary' => [
        'headers' => ['Accept-Language', 'X-Tenant-Id'],
    ],
],
```

### 5.2 Ventajas

- No depende del cache normal de la app.
- `php artisan cache:clear` no borra necesariamente la metadata ADP compilada.
- Permite tratar ADP como artifact de deploy, parecido a config/routes cache.
- Reduce introspeccion runtime de modelos, rutas y validaciones.

### 5.3 Comandos

```bash
php artisan agent:cache
php artisan agent:clear
php artisan agent:cache --tenant=7
php artisan agent:clear --tenant=7
php artisan agent:cache --locale=es
php artisan agent:cache --tenant=7 --locale=es
php artisan agent:cache --only=references
```

`--only=references` expresa la intencion de refrescar referencias. En esta
implementacion recompila el grafo porque las referencias viven dentro de los
campos del recurso.

### 5.4 Variacion Por Tenant O Locale

Si se ejecuta:

```bash
php artisan agent:cache --tenant=7 --locale=es
```

la cache se guarda con una variacion basada en headers:

```php
[
    'headers' => [
        'X-Tenant-Id' => '7',
        'Accept-Language' => 'es',
    ],
]
```

Esto permite que diferentes tenants o idiomas tengan metadata distinta sin
pisarse entre si.

### 5.5 Flujo De Deploy Recomendado

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan agent:validate
php artisan agent:cache
```

Si la metadata depende de tenants:

```bash
php artisan agent:cache --tenant=1
php artisan agent:cache --tenant=2
php artisan agent:cache --tenant=3
```

## 6. Endpoint `/agent/bundle`

El nuevo endpoint compacta discovery para consumidores que prefieren cargar una
sola fuente de verdad.

```text
GET /agent/bundle?mode=full
GET /agent/bundle?mode=slim
```

### 6.1 Modo `full`

Incluye todo el grafo, incluyendo `reference.inline_values`.

Uso recomendado:

- primera carga del agente
- primera ejecucion de un workflow n8n
- MCP server al iniciar
- generacion de contexto completo para pruebas

### 6.2 Modo `slim`

Elimina `reference.inline_values` pero conserva:

- `resource`
- `lookup_field`
- `display_fields`
- `complete`
- `hint`

Uso recomendado:

- consumidores que ya cachearon catalogos
- agentes con presupuesto de tokens limitado
- refresh frecuente por ETag
- integraciones donde las referencias se consultan bajo demanda

### 6.3 Headers De Revalidacion

Todas las respuestas de metadata agregan:

- `ETag`
- `Last-Modified`

Esto permite que un consumidor haga cache local y evite descargar metadata
completa en cada ejecucion.

Flujo recomendado:

1. Consumidor llama `/agent/bundle?mode=full`.
2. Guarda payload + `ETag`.
3. En ejecuciones siguientes revalida.
4. Si cambio, actualiza cache local.
5. Si no cambio, usa cache local.

## 7. MCP Exporter Y Annotations

El exporter MCP ahora publica hints estandar y metadata ADP extendida.

### 7.1 Ejemplo De Tool Exportada

```json
{
  "name": "delete_security_user",
  "description": "Delete users.",
  "inputSchema": {
    "type": "object",
    "additionalProperties": true
  },
  "annotations": {
    "readOnlyHint": false,
    "destructiveHint": true,
    "idempotentHint": true,
    "openWorldHint": false
  },
  "x-adp": {
    "risk_level": "high",
    "requires_confirmation": true,
    "permissions": ["security.user.delete"],
    "side_effects": [],
    "source": "adp://resources/security.user/operations/delete"
  }
}
```

### 7.2 Mapeo Base

| Operacion ADP | readOnlyHint | destructiveHint | idempotentHint |
|---|---:|---:|---:|
| `query`, `index`, `show`, `query_one` | true | false | true |
| `create`, `bulk_create` | false | false | false |
| `update` | false | false | true |
| `delete`, `force_delete` | false | true | true |
| `bulk_update`, `*_multiple` | false | true | depende del metodo |
| `restore` | false | false | true |

### 7.3 Overrides

Una operacion puede declarar annotations manuales:

```php
'operations' => [
    'archive' => [
        'method' => 'POST',
        'endpoint' => '/api/security/users/{id}/archive',
        'risk' => 'high',
        'requires_confirmation' => true,
        'annotations' => [
            'readOnlyHint' => false,
            'destructiveHint' => true,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ],
    ],
],
```

`agent:validate` detecta overrides peligrosos, por ejemplo una operacion
delete-like marcada como `destructiveHint=false`.

## 8. Validacion Avanzada

`agent:validate` ahora protege contratos que pueden inducir errores en agentes.

Detecta:

- operaciones `high` o `critical` sin `requires_confirmation=true`;
- operaciones de alto riesgo marcadas como read-only;
- operaciones delete-like con `destructiveHint=false`;
- campos `enum` sin `enum_values`;
- enum values sin `value` o sin `label`;
- referencias sin `resource`;
- referencias sin `lookup_field`;
- `reference.complete` con tipo no booleano;
- campos sensibles visibles sin exposicion explicita.

Ejecutar en CI:

```bash
php artisan agent:validate
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

## 9. Integracion Con n8n

n8n no debe contener logica de negocio. Debe actuar como puente:

1. Recibe input del usuario, webhook o chatbot.
2. Carga ADP desde `/agent/bundle`.
3. Cachea metadata en workflow/node state.
4. Pasa metadata relevante al LLM.
5. El LLM decide recurso, operacion y payload.
6. n8n llama la API real con token, tenant y locale.
7. La API autoriza, valida y ejecuta.

### 9.1 Flujo De Query

```text
Webhook/Chat
  -> Load ADP Bundle
  -> LLM Resolve Intent
  -> HTTP Request To API
  -> Format Response
```

### 9.2 Flujo De Mutation

```text
Webhook/Chat
  -> Load ADP Bundle
  -> LLM Resolve Intent
  -> Check risk/requires_confirmation
  -> Human confirmation if needed
  -> HTTP Request To API
  -> Format Response
```

### 9.3 Cache En n8n

Usar `full` en la primera carga:

```text
GET /agent/bundle?mode=full
```

Usar `slim` cuando ya existen referencias cacheadas:

```text
GET /agent/bundle?mode=slim
```

Guardar `ETag` y `Last-Modified` para saber si la metadata cambio.

## 10. Integracion Con Agentes Directos

Un agente propio puede usar este flujo:

1. Autenticarse con API key o bearer token.
2. Cargar `/agent/bundle?mode=full`.
3. Reducir contexto al modulo/recurso probable.
4. Pedir al LLM que elija:
   - `resource`
   - `operation`
   - filtros
   - relaciones
   - payload
5. Validar localmente contra ADP.
6. Ejecutar contra la API.
7. Si hay riesgo alto, pedir confirmacion antes de ejecutar.

Prompt interno recomendado:

```text
Use only fields, operations, relations and enum values published by ADP.
If a required reference has complete=false, query the referenced resource first.
Do not call high or critical operations without explicit confirmation.
```

## 11. Integracion Con MCP

Este paquete no es un MCP server. Produce metadata/export que un MCP server
puede consumir.

Patron recomendado:

1. MCP server carga ADP.
2. Expone resources `adp://resources/{key}`.
3. Expone tools derivadas de operaciones ADP.
4. Usa annotations MCP estandar para seguridad generica.
5. Usa `x-adp` para UX avanzada: confirmaciones, permisos, risk badges y logs.

El server MCP debe respetar:

- `readOnlyHint`
- `destructiveHint`
- `idempotentHint`
- `openWorldHint`
- `x-adp.requires_confirmation`
- `x-adp.risk_level`

## 12. Seguridad

ADP no reemplaza seguridad backend.

Debe asumirse:

- el token pertenece a un usuario, tenant o API key real;
- los scopes se aplican en la API;
- policies/middleware siguen siendo fuente de verdad;
- ADP describe permisos, pero no los concede;
- operaciones high/critical requieren confirmacion metadata-side y backend-side.

No publicar:

- passwords;
- secrets;
- tokens;
- recovery codes;
- campos internos de auditoria si no son necesarios;
- tablas de referencia con PII sensible.

## 13. Buenas Practicas De Diseno

### 13.1 Campos

Publicar `label` y `description` en campos ambiguos:

- `status`
- `type`
- `kind`
- `state`
- `category_id`
- `role_id`
- `owner_id`
- `tenant_id`

### 13.2 Enums

Todo enum expuesto debe tener:

- `value`
- `label`
- `description` cuando la diferencia semantica no sea obvia

Ejemplo:

```php
[
    'value' => 'voided',
    'label' => 'Voided',
    'description' => 'Invoice was invalidated and should not be collected.',
]
```

Esto evita que el LLM confunda `cancelled`, `voided`, `overdue` y `paid`.

### 13.3 Referencias

Usar inline solo cuando:

- la tabla es pequena;
- no contiene datos sensibles;
- cambia poco;
- ayuda a resolver lenguaje natural.

No usar inline para:

- clientes;
- productos grandes;
- usuarios completos;
- transacciones;
- datos financieros sensibles.

## 14. Troubleshooting

### El agente inventa campos

Revisar:

```bash
php artisan agent:discover --json
php artisan agent:validate
```

Confirmar que el campo existe en `fields` y que el prompt del consumidor obliga
a usar solo metadata publicada.

### El agente no resuelve un enum

Agregar `label` y `description` a cada `enum_values`.

### El agente no resuelve "departamento ventas"

Configurar `reference_tables.departments` con:

- `lookup_field=name`
- `foreign_keys=['department_id']`
- `values` o `model`

### El bundle pesa demasiado

Usar:

```text
GET /agent/bundle?mode=slim
```

Reducir `max_records` en reference tables grandes.

### La metadata esta desactualizada

Recompilar:

```bash
php artisan agent:cache
```

Para tenant:

```bash
php artisan agent:cache --tenant=7
```

## 15. Checklist De Produccion

- `agent:validate` pasa en CI.
- `vendor/bin/pest` pasa.
- `vendor/bin/phpstan analyse` pasa.
- `vendor/bin/pint --test` pasa.
- cache `compiled_file` configurada si se necesita metadata persistente.
- `/agent/bundle` protegido por middleware apropiado.
- campos sensibles redacted.
- enums con labels.
- referencias con `max_records` razonable.
- n8n/MCP cachean bundle por `ETag`.
- operaciones high/critical requieren confirmacion.
- documentacion end-to-end validada con un caso real.

## 16. Resumen Operativo

Para un proyecto real, el orden recomendado es:

1. Configurar recursos o habilitar route discovery.
2. Agregar labels/descripciones a campos ambiguos.
3. Agregar enum values ricos.
4. Configurar reference tables pequenas.
5. Ejecutar `agent:validate`.
6. Activar cache.
7. Consumir `/agent/bundle?mode=full`.
8. Integrar n8n/MCP/agente directo.
9. Medir tasa de resolucion, tokens y fallos por metadata.
