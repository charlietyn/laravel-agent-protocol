# MCP And n8n

This package prepares metadata for MCP and n8n, but it is not itself an MCP
server or n8n node package.

## MCP Mapping

| ADP | MCP |
|---|---|
| `ResourceDescriptor` | MCP resource, for example `adp://resources/security.user`. |
| `OperationDescriptor query` | Low-risk tool, for example `query_security_user`. |
| `OperationDescriptor create` | Tool with validation and confirmation metadata. |
| Dictionary | Resource or prompt context. |
| Examples | Prompt templates or tool examples. |

Tool names must be deterministic: `{operation}_{resource}` normalized to
letters, numbers and underscores.

MCP exports include standard tool annotations:

| ADP operation | `readOnlyHint` | `destructiveHint` | `idempotentHint` |
|---|---:|---:|---:|
| `query`, `show` | true | false | true |
| `create` | false | false | false |
| `update` | false | false | true |
| `delete`, `force_delete` | false | true | true |
| `bulk_update` | false | true | false |
| `restore` | false | false | true |

ADP-specific metadata is exported under `x-adp`, including risk level,
confirmation, permissions, side effects and source URI.

## n8n Mapping

n8n nodes should use ADP discovery to populate dynamic options:

- resources from `/agent/resources`;
- operations from `/agent/resources/{resource}/operations`;
- payload forms from `ValidationDescriptor`;
- relation selects from `RelationDescriptor`;
- filter UI from `/agent/documentation/filter`;
- confirmation controls from operation risk metadata.

Credentials, tenant headers and locale headers belong in n8n credentials or node
configuration, not in prompts.

n8n should load `/agent/bundle?mode=full` for first discovery, cache the result
in workflow or node state, and prefer `/agent/bundle?mode=slim` once lookup
values are already cached.

For complete MCP/n8n flows, risk handling and cache strategy, see
[Guia Avanzada De Uso E Integracion](GUIA_AVANZADA.md).
