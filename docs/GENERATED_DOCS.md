# Generated Documentation

`agent:docs` writes Markdown files from the compiled graph.

```bash
php artisan agent:docs docs/generated
```

Generated files:

| File | Source |
|---|---|
| `overview.md` | Protocol, module and resource summary. |
| `resources.md` | Resource descriptors and operation tables. |
| `filters.md` | Filter operators, condition format and limits. |
| `errors.md` | ADP error catalog. |
| `mcp-tools.md` | MCP-style manifest derived from ADP. |

Rules:

- Do not manually document fields that the compiler can discover.
- Mark conceptual examples as conceptual.
- Keep generated docs out of hand-maintained source unless intentionally
  committed as snapshots.
- Do not publish sensitive fields in public documentation.
