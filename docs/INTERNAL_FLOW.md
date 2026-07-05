# Internal Flow

```mermaid
sequenceDiagram
    participant CLI as Artisan/HTTP
    participant Repo as MetadataRepository
    participant Compiler as MetadataCompiler
    participant Passes as Compiler Passes
    participant Builder as AgentMetadataGraphBuilder
    participant Graph as AgentMetadataGraph

    CLI->>Repo: get() or refresh()
    Repo->>Compiler: compile()
    Compiler->>Passes: compile(context, builder)
    Passes->>Builder: add resources/docs/dictionary
    Builder->>Graph: build()
    Graph-->>Repo: immutable graph
    Repo-->>CLI: cached or fresh graph
```

## Why This Flow

Reflection and route scanning are expensive and should not happen on every ADP
HTTP request. `MetadataRepository` owns cache behavior, while controllers read
from registries backed by the already-compiled graph.
