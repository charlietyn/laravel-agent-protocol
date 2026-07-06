# Contributing

## Local Checks

```bash
composer install
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
```

## Design Rules

- Do not execute API operations inside ADP endpoints.
- Do not duplicate `rest-generic-class` filtering, relation or CRUD logic.
- Compile metadata first, then serve it from registries.
- Prefer small compiler passes over large services.
- Keep DTOs immutable.
- Add tests for every new protocol field or compiler behavior.

## Pull Request Checklist

- Tests added or updated.
- Documentation updated from real behavior.
- `agent:validate` still passes.
- New extension points have a stable contract.
- Release-impacting changes update [Release and Publishing](RELEASE.md) when
  the publishing flow changes.
