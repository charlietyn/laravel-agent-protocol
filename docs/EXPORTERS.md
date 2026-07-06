# Exporters

Exporters transform `AgentMetadataGraph` into other metadata formats. They do
not execute API operations.

Configured exporters:

| Format | Class | Use |
|---|---|---|
| `json` | `JsonMetadataExporter` | Native ADP graph. |
| `json-schema` | `JsonSchemaMetadataExporter` | Operation input schemas from validation rules. |
| `markdown` | `MarkdownMetadataExporter` | Human documentation. |
| `mcp` | `McpManifestExporter` | MCP-style resources/tools manifest. |

The MCP exporter emits standard tool annotations (`readOnlyHint`,
`destructiveHint`, `idempotentHint`, `openWorldHint`) and keeps ADP-specific
risk and confirmation metadata under `x-adp`.

Usage:

```bash
php artisan agent:export agent-metadata.json --format=json
php artisan agent:export operation-schemas.json --format=json-schema
php artisan agent:export mcp-manifest.json --format=mcp
```

Add custom exporters by implementing
`Ronu\LaravelAgentProtocol\Exporters\MetadataExporter` and registering the class
in `config/agent-protocol.php`.
