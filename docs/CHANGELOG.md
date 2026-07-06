# Changelog

## Unreleased

- Added operation risk metadata and confirmation requirements.
- Added resource readiness scoring.
- Added sensitive-field redaction and visibility metadata.
- Added filter limits from `rest-generic-class`.
- Added cache key variation by configured request headers.
- Added `agent:discover` and `agent:docs`.
- Added export formats: JSON Schema, Markdown and MCP-style manifest.
- Added feature tests for endpoints and CLI commands.
- Added security, readiness, CLI, exporter and MCP/n8n documentation.

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
