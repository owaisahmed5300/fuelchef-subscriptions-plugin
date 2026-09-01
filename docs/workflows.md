# CI & release workflows

What each workflow is for and the decisions the YAML cannot state for itself. How to
respond when one fails is [`guidelines/05-ci-cd.md`](guidelines/05-ci-cd.md).

Workflows run natively via `shivammathur/setup-php` rather than the dev image — faster
across a matrix — and `scripts/scope` is the seam that keeps local and CI identical. Each
declares least-privilege `permissions` and cancels superseded runs via `concurrency`.

`lint.yml` and `test.yml` both carry `paths-ignore`, so a docs-only change burns no
matrix. Each ignores the other's config plus `docs/**`, `**.md`, `LICENSE`, the Husky
hooks and the Docker environment. **Adding a top-level config file usually means adding it
to one of those lists.**

## `lint.yml`

Three jobs on every PR and push to `main`: PHPCS and PHPStan, an editorconfig check, and
`legacy-parse`, which lints the pre-flight files under a real PHP 5.6 — the only check
with [no blind spots](standards.md#the-pre-flight-files-target-php-56). How each is
configured, and why the editorconfig job disables two checks:
[`standards.md`](standards.md).

PHPCS reports through `cs2pr` and PHPStan with `--error-format=github`, so violations
appear as inline annotations rather than buried in a log.

> **Why lint scopes first.** `plugin/src` imports `Deps\` symbols that exist only in the
> gitignored `plugin/vendor-prefixed/`, and `phpstan.neon.dist` scans that directory.
> Without the scope step PHPStan reports every scoped class as unknown. It runs the *same*
> `scripts/scope` as local and release, so the three cannot drift.

## `test.yml`

The unit suite across PHP `8.0`–`8.5`, Composer cache keyed on `composer.lock`. No
database, no WordPress. To cover another version, add it to the matrix `php:` list.

## `release.yml`

Triggered by a `v*` tag. It parses the tag, generates the translation template, scopes the
dependencies, zips `plugin/` as `<slug>-<version>.zip` with the Composer manifests
stripped, and publishes a GitHub release with the zip attached. A hyphenated tag
(`v1.2.3-rc.1`) publishes as a pre-release.

Four things about it are deliberate and worth knowing before you change it:

- **It fails the build when the tag and the plugin file disagree.** The tag's base version
  must equal both the `Version:` header and the `*_VERSION` constant. This is the only
  thing standing between a mistyped tag and a release whose zip reports a different
  version than its filename.
- **The POT is generated before scoping**, so only first-party source is scanned.
- **It runs `scripts/scope`, not a CI-only equivalent** — the same script used locally, so
  a release is scoped exactly as development is.
- **It finds the main plugin file by its `Plugin Name:` header**, not by a hardcoded name,
  so a plugin renamed with `scripts/setup` still releases.

It sets up PHP 8.3 because php-scoper needs ≥ 8.2, while the shipped plugin still targets
8.0. Expression values reach the shell steps through `env:` rather than being interpolated
into the script body, so a crafted tag cannot inject shell.

To cut a release, see
[`guidelines/05-ci-cd.md`](guidelines/05-ci-cd.md#releasing).
