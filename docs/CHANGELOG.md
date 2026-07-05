# Changelog

## 0.1.0

- Added Laravel service provider and package configuration.
- Added ADP routes under `/agent`.
- Added immutable metadata DTOs.
- Added `MetadataCompiler` and compiler passes.
- Added configured resource discovery.
- Added route discovery for `RestController`-style controllers.
- Added model, relation and validation introspection.
- Added cache repository.
- Added registries used by HTTP controllers.
- Added Artisan commands: `agent:cache`, `agent:clear`, `agent:validate`,
  `agent:export`.
- Added JSON exporter.
- Added initial PHPUnit/Pest-compatible tests.
