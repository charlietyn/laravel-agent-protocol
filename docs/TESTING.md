# Testing

The package ships with a test setup intended for Pest/PHPUnit.

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

## Current Tests

`tests/Unit/DtoSerializationTest.php`
: Verifies that DTOs serialize into the ADP envelope.

`tests/Unit/MetadataCompilerTest.php`
: Verifies that `ConfiguredResourcePass` can compile a configured resource,
model fields, scenario operations and capabilities.

`tests/Unit/ProtocolValidatorTest.php`
: Verifies valid graphs and broken module references.

## Test Strategy

Unit tests should cover:

- DTO serialization.
- Model introspection.
- Validation rule normalization.
- Operation inference.
- Compiler pass merging.
- Protocol validation.

Feature tests should cover:

- `GET /agent`
- `GET /agent/resources`
- `GET /agent/resources/{resource}`
- `GET /agent/resources/{resource}/operations/{scenario}`
- Cache behavior through `agent:cache` and `agent:clear`.

Integration tests should use a small Laravel fixture app with
`ronu/rest-generic-class` installed and controllers extending `RestController`.

## Local Limitation

This repository targets PHP `^8.3`. If your local CLI is PHP 8.2, syntax checks
can still pass, but Composer install and the full test suite should be run with
PHP 8.3 or newer.
