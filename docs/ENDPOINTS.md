# Endpoints

## `GET /agent`

Returns the full `AgentMetadataGraph`, including protocol version,
implementation metadata, capabilities, links, modules, resources, filter
documentation and dictionary.

## `GET /agent/bundle?mode=full|slim`

Returns the full graph with a `bundle.mode` marker. `full` includes inline
reference values. `slim` removes `reference.inline_values` and keeps the lookup
schema and hints.

Bundle responses include `ETag` and `Last-Modified` headers for consumer cache
revalidation.

See [Guia Avanzada De Uso E Integracion](GUIA_AVANZADA.md) for when to use
`full` versus `slim`.

## `GET /agent/modules`

Returns all module descriptors.

## `GET /agent/resources`

Returns all resource descriptors.

## `GET /agent/resources/{resource}`

Returns one resource descriptor by key, for example `security.user`.
The descriptor includes fields, relations, operations, filters, security
metadata, readiness and capabilities.

## `GET /agent/resources/{resource}/operations`

Returns all operation descriptors for a resource.

## `GET /agent/resources/{resource}/operations/{scenario}`

Returns a single operation descriptor for one scenario.
The descriptor includes validation, risk, permissions and
`requires_confirmation`.

## `GET /agent/documentation/filter`

Returns the `rest-generic-class` query grammar metadata.
This includes operators, parameters, condition format, examples and operational
limits such as `max_depth` and `max_conditions`.

## `GET /agent/documentation/errors`

Returns ADP error documentation configured in `agent-protocol.documentation`.

## `GET /agent/dictionary`

Returns optional business glossary entries from dictionary providers.
