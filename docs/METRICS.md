# Success Metrics

Use these metrics to evaluate whether ADP improves agent integration.

## Agent Quality

- Query resolution rate: prompts correctly converted into API operations.
- Metadata failure rate: failures caused by missing fields, enums or references.
- Unsupported intent rate: prompts rejected because no published operation exists.

## Efficiency

- Average tokens per interaction before and after ADP.
- Discovery HTTP calls per interaction.
- Cache hit rate in n8n, MCP server or agent runtime.
- Median `/agent/bundle` response size in `full` and `slim` modes.

## Adoption

- Time to onboard a new resource into agent workflows.
- Resources with readiness score above 70.
- Resources with enum labels and reference metadata.
- Manual prompt lines removed after switching to ADP discovery.

## Safety

- High or critical operations blocked without confirmation.
- Sensitive fields redacted from metadata.
- Authorization failures returned by the backend during agent execution.
- Stale metadata incidents after deploy or reference-table changes.
