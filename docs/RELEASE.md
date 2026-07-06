# Release and Publishing

This package is published as a Composer library through Packagist. Composer
versions must come from Git tags, not from a `version` field in `composer.json`.

## One-Time Publishing Setup

1. Ensure the repository is public and `composer.json` contains the final
   package name, description, license, autoload rules, keywords and support
   links.
2. Submit the repository to Packagist manually, or run the release script once
   with `--create-packagist` and a Packagist main API token.
3. Configure automatic updates through the Packagist GitHub app or webhook.
4. Add these GitHub repository secrets if the release workflow should notify
   Packagist when a tag is pushed:

```text
PACKAGIST_USERNAME
PACKAGIST_TOKEN
PACKAGIST_REPOSITORY
```

`PACKAGIST_REPOSITORY` is optional when the repository is
`https://github.com/charlietyn/laravel-agent-protocol.git`.

## Normal Release Flow

Run a dry run first:

```bash
php scripts/release.php --version=0.1.0 --dry-run
```

Publish the release:

```bash
php scripts/release.php --version=0.1.0
```

The script performs these steps:

- validates that `composer.json` has no `version` field;
- verifies the Git branch and clean working tree;
- checks that the release tag does not already exist locally or remotely;
- runs `composer validate --strict`;
- runs Pint, PHPStan and Pest;
- creates an annotated tag such as `v0.1.0`;
- pushes the branch and tag;
- notifies Packagist when credentials are configured.

## Script Options

```bash
php scripts/release.php --version=0.1.0 \
  --branch=main \
  --remote=origin \
  --tag-prefix=v
```

Useful options:

- `--dry-run`: print commands and API calls without changing Git or Packagist.
- `--allow-dirty`: allow local uncommitted changes intentionally.
- `--skip-format`: skip Pint.
- `--skip-static`: skip PHPStan.
- `--skip-tests`: skip Pest.
- `--with-rector`: run Rector in dry-run mode.
- `--skip-tag`: do not create a local Git tag.
- `--no-push`: do not push branch or tag.
- `--no-packagist`: do not call the Packagist API.
- `--create-packagist`: create the Packagist package before updating it.
- `--force-packagist`: call Packagist even if tag or push was skipped.
- `--packagist-repository=URL`: override the repository sent to Packagist.

Environment variables:

```text
RELEASE_REMOTE
RELEASE_BRANCH
RELEASE_TAG_PREFIX
PACKAGIST_USERNAME
PACKAGIST_TOKEN
PACKAGIST_REPOSITORY
```

Packagist bearer authentication uses:

```text
Authorization: Bearer {PACKAGIST_USERNAME}:{PACKAGIST_TOKEN}
```

## First Publication

For the first publication, prefer the Packagist web UI or the official GitHub
integration. If the script is used to create the package, use a main Packagist
API token and run:

```bash
PACKAGIST_USERNAME=your-user \
PACKAGIST_TOKEN=your-main-token \
php scripts/release.php --version=0.1.0 --create-packagist
```

After the package exists on Packagist, use a safe package token for normal
updates.

## Reusing The Script In Another PHP Library

Copy `scripts/release.php` to the target Composer library and make sure the
library has:

- a valid `composer.json` at the repository root;
- no `version` field in `composer.json`;
- Git tags following `vX.Y.Z` or configure `--tag-prefix`;
- Composer scripts or binaries for Pint, PHPStan, Rector and Pest, or use the
  skip flags for tools that do not apply;
- Packagist credentials only when API sync is required.

## Verification Commands

```bash
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
```

Rector is useful before larger code cleanups, but it is optional for publishing:

```bash
vendor/bin/rector process --dry-run
```

## References

- Packagist API: https://packagist.org/apidoc
- Packagist publishing overview: https://packagist.org/about
- Composer versions and tags: https://getcomposer.org/doc/articles/versions.md
