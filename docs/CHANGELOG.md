# Changelog

## Unreleased

## 0.2.2 - 2026-08-20

- Added Laravel 13 support, including Testbench 11, Pest 4 and PHPUnit 12.

## 0.2.1 - 2026-07-09

- Added package publication metadata for Packagist and GitHub.
- Added MIT license file and Composer dist export rules.
- Added reusable `scripts/release.php` for Composer library releases.
- Added tag-driven GitHub release workflow with optional Packagist sync.
- Added release and publishing documentation.
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
