# scripts/

The `scripts/` directory holds every executable and configuration file that drives
development, testing, CI, and releases. Everything is pure POSIX `sh` and runs on
Linux, macOS, or Git Bash on Windows.

```
scripts/
├── dev               # Single entry point for local development (Docker front-end)
├── setup             # One-time interactive wizard to rename the boilerplate
├── scope             # Build plugin/vendor-prefixed/ with php-scoper
├── docker/           # Docker Compose environment
│   ├── compose.yml
│   ├── .env.example
│   ├── .env          # (gitignored) local overrides
│   ├── php/          # PHP Dockerfile & config
│   └── wordpress/    # WordPress Dockerfile & mu-plugins
└── lib/
    └── runtime       # Shared Docker helpers (sourced by dev)
```

## `dev` — the developer CLI

The primary interface for local work. All commands run inside Docker containers so
the only host requirement is Docker itself.

The ones used most — `./scripts/dev help` lists them all, and is the copy that cannot go
stale:

```
./scripts/dev up                 Start WordPress, DB, phpMyAdmin, Mailpit
./scripts/dev shell tools        Interactive shell in the tools container
./scripts/dev composer install   Install dev tooling dependencies
./scripts/dev plugin install     Install plugin runtime deps (auto-scopes)
./scripts/dev phpcs              Coding-standard check
./scripts/dev phpstan            Static analysis
./scripts/dev preflight          Syntax-check the pre-flight files on PHP 5.6
./scripts/dev test               Unit tests
```

## `preflight` — old-PHP syntax check

`./scripts/dev preflight` runs `php -l` on `plugin/fuelchef-subscriptions.php` and
`plugin/src/Requirements.php` under a real **PHP 5.6** (`php:5.6-cli`), matching the CI
`legacy-parse` job. The files themselves are written for **PHP 5.3** (maximum reach for
the pre-flight gate), but 5.6 is the oldest interpreter `shivammathur/setup-php` and the
official Docker image still make reliably available — 5.3 is no longer installable on
current GitHub-hosted runners and its Docker images are abandoned. 5.6 parses every
construct 5.3 does, so passing it proves the files hold no modern syntax. Set the
`PREFLIGHT_IMAGE` env var to override the image (e.g. `php:7.0-cli`) if you ever need to.

## `setup` — rename wizard

Run once after cloning to personalise the plugin — name, slug, namespace, constant
prefix, scoper prefix, and Docker host ports. Pure shell, no Docker or PHP needed.

```
./scripts/dev setup           # full wizard (rename + Docker env)
./scripts/dev setup --env     # configure Docker env only (copy .env.example → .env,
                              #   prompt for ports). Works on already-renamed projects.
./scripts/dev setup --force   # re-run rename even if the boilerplate looks modified
```

## `scope` — dependency scoping

Builds `plugin/vendor-prefixed/` — the autoloader the plugin actually loads at
runtime. Third-party dependencies are namespaced under `FuelChef\Subscriptions\Dependencies\`
so releases cannot collide with other plugins.

```
./scripts/dev scope
```

Triggered automatically by `./scripts/dev plugin install/update/require/remove`.

## `docker/` — Compose environment

- **compose.yml** — defines the `wordpress`, `db` (MySQL 8), `phpmyadmin`, `mailpit`,
  and `tools` services. The `tools` service bakes PHP, Composer, WP-CLI and php-scoper
  into a single image for fast one-shot commands.
- **.env** — local port and credential overrides (gitignored; `.env.example` is the
  template edited by `setup`).

## `lib/runtime` — shared helpers

Sourced by `dev` to provide Docker Compose helper functions (`compose`, `compose_run`,
`require_docker`). Not meant to be called directly.

---

[`AGENTS.md`](../AGENTS.md) is the map of everything else in the repository.
