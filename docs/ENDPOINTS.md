# Endpoints

## `GET /agent`

Returns the full `AgentMetadataGraph`.

## `GET /agent/modules`

Returns all module descriptors.

## `GET /agent/resources`

Returns all resource descriptors.

## `GET /agent/resources/{resource}`

Returns one resource descriptor by key, for example `security.user`.

## `GET /agent/resources/{resource}/operations`

Returns all operation descriptors for a resource.

## `GET /agent/resources/{resource}/operations/{scenario}`

Returns a single operation descriptor for one scenario.

## `GET /agent/documentation/filter`

Returns the `rest-generic-class` query grammar metadata.

## `GET /agent/documentation/errors`

Returns ADP error documentation configured in `agent-protocol.documentation`.

## `GET /agent/dictionary`

Returns semantic aliases from dictionary providers.
