# FuelChef Subscriptions

**FuelChef Subscriptions** is a WordPress plugin that ships with a batteries-included
development setup: a Dockerised dev environment, coding standards, static analysis, unit
tests, dependency scoping, and CI/release automation, all wired up and ready to use, so
development is productive from the first commit.

It also ships the wiring most plugins end up writing by hand: a dependency-injection
container, a requirements gate that refuses to load with a helpful admin notice when
WordPress or a required plugin is missing or too old, and a versioned database installer
with migrations.

## Requirements

- **Docker** (Desktop or Engine, with the Compose v2 plugin).

That's it: PHP, Composer, MySQL and WP-CLI all run in containers. No host PHP needed.

## Quick start

```sh
# 1. Make it yours: interactive wizard sets name, slug, namespace, text domain, ports.
./scripts/dev setup

# 2. Install the dev tooling
./scripts/dev composer install

# 3. Install the plugin's runtime deps. This also builds plugin/vendor-prefixed/,
#    which the plugin loads its code from, so it is not optional.
./scripts/dev plugin install

# 4. Start the stack
./scripts/dev up

# 5. Run checks and tests
./scripts/dev phpcs
./scripts/dev phpstan
./scripts/dev test    # creates the test database automatically
```

For the full command reference and details about every script (environment, scoping,
test setup, Docker config), see the [scripts README](scripts/README.md).

## Documentation

- [Scripts README](scripts/README.md) — the development CLI and scripts folder.
- [Tests README](tests/README.md) — where test files go.
- [AGENTS.md](AGENTS.md) — the map: structure, commands, and the rules that bite.
- [Architecture docs](docs/architecture.md) — structure, autoloading, scoping.
- [Testing docs](docs/testing.md) — the unit suite in depth.
- [Workflows docs](docs/workflows.md) — CI and the release pipeline.

## Releasing

Bump the `Version:` header and the `*_VERSION` constant in the main plugin file, then:

```sh
git tag v1.2.3
git push origin v1.2.3
```

The release workflow verifies the version, scopes dependencies, generates the `.pot`
file, and publishes a GitHub release with the plugin zip. Hyphenated tags
(`v1.2.3-beta.1`) publish as pre-releases.

## License

GPL-2.0-or-later.
