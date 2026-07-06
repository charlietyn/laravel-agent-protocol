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
