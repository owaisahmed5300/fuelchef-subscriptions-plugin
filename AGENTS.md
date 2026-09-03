# AGENTS.md

Entry point for humans and AI agents working in this repository. It says what this
template is, how to run it, and where everything else is written down. It deliberately
does not repeat those documents: when a fact appears twice, the copies drift, and nothing
tests a document.

## Where things are

| I want to…                                               | Read                                                                 |
|----------------------------------------------------------|----------------------------------------------------------------------|
| Set this up as my own plugin                             | [Making it yours](#making-it-yours)                                  |
| Know how to work here — branching, commits, tests, CI    | [`docs/guidelines/`](docs/guidelines/README.md)                      |
| Run, test, lint or release                               | [`scripts/README.md`](scripts/README.md)                             |
| Understand the repository layout, autoloading or scoping | [`docs/architecture.md`](docs/architecture.md)                       |
| Configure or debug PHPCS and PHPStan                     | [`docs/standards.md`](docs/standards.md)                             |
| Write a test                                             | [`docs/testing.md`](docs/testing.md)                                 |
| Fix a CI failure or cut a release                        | [`docs/workflows.md`](docs/workflows.md)                             |
| Know where your own product docs go                      | [`docs/README.md`](docs/README.md#where-your-own-documentation-goes) |

**Read [`docs/guidelines/README.md`](docs/guidelines/README.md) before your first
change.** Its ten rules are one screen, and they are what review holds work to.

---

## What this repository is

A WordPress plugin skeleton plus the tooling used to develop, test, lint and release it.
It is split in two:

- **`plugin/`** — what ships. Everything under it, and nothing else, goes into the
  release zip.
- **Everything else** — tooling, tests, CI and configuration that never ships.

That split is why there are two `composer.json` files: the root one installs dev tooling
and provides the autoloader used by tests and analysis, and `plugin/composer.json`
declares only the plugin's runtime dependencies. Full mechanism:
[`docs/architecture.md`](docs/architecture.md).

It also ships the wiring most plugins write by hand: a dependency-injection container, a
pre-flight requirements gate that explains itself in an admin notice, and a versioned
database installer.

```
.
├── plugin/                       # The distributable plugin (this is what ships)
│   ├── fuelchef-subscriptions.php # Main file: headers, constants, gate, bootstrap
│   ├── uninstall.php             # Runs on uninstall (bare WP context, self-contained)
│   ├── src/                      # PSR-4 classes (FuelChef\Subscriptions\ => src/)
│   ├── assets/  templates/  languages/
│   └── composer.json             # The plugin's *runtime* dependencies only
│
├── docs/                         # How this repo works, and where your docs go
│   ├── guidelines/               # HOW we work — read before your first change
│   ├── architecture.md  standards.md  testing.md  workflows.md
│
├── tests/                        # Unit tests, base class and bootstraps
├── scripts/                      # dev CLI, setup wizard, scope, Docker environment
├── .github/workflows/            # lint.yml, test.yml, release.yml, commitlint.yml
└── composer.json  phpcs.xml.dist  phpstan.neon.dist  phpunit.xml.dist  scoper.inc.php
```

---

## Making it yours

**The only thing you need installed is Docker** (Desktop or Engine, with Compose v2).
PHP, Composer, MySQL and WP-CLI all run in containers.

```sh
./scripts/dev setup                   # rename every placeholder (interactive, once)
./scripts/dev composer install        # install dev tooling
./scripts/dev plugin install          # install runtime deps and build vendor-prefixed/
./scripts/dev up                      # start the site
./scripts/dev test                    # confirm everything works
```

`./scripts/dev setup` is a wizard: plugin name, slug and text domain, namespace, coding
style, and the local Docker ports. It rewrites every placeholder across the source,
config, tooling and docs, renames the main plugin file, and refuses to run twice without
`--force`. Details: [`scripts/README.md`](scripts/README.md#setup--rename-wizard).

`./scripts/dev plugin install` is not optional. The plugin loads its code from
`plugin/vendor-prefixed/`, which is gitignored, so a fresh checkout cannot boot until you
have built it.

Every tooling command spins up a throwaway container, adding ~100–200 ms. To run several
in a row, open a shell instead: `./scripts/dev shell tools`.

### Two execution contexts, one script layer

| Context             | How PHP tooling runs                  | Entry point       |
|---------------------|---------------------------------------|-------------------|
| Local development   | Inside Docker containers              | `./scripts/dev …` |
| CI (GitHub Actions) | Natively via `shivammathur/setup-php` | the workflows     |

`scripts/scope` assumes `php`, `composer` and `php-scoper` are already on `PATH`, so the
same code runs in both. Only `scripts/dev` knows about Docker.

---

## The rules that bite

These are the ones whose damage is expensive to undo. The rest, and the reasoning behind
all of them, is in [`docs/guidelines/`](docs/guidelines/README.md).

- **Never commit to `main`.** Branch, then open a pull request.
- **Never commit generated artifacts** — `vendor/`, `plugin/vendor/`,
  `plugin/vendor-prefixed/`, `plugin/composer.lock`, `node_modules/`, build zips, caches.
- **Import the prefixed namespace in plugin code**
  (`FuelChef\Subscriptions\Dependencies\WPTechnix\DI\Container`). The unprefixed name resolves
  against the dev tree and then fatals in a release.
- **Keep the four PHP-version declarations in step**
  ([`docs/standards.md`](docs/standards.md#the-php-floor-is-declared-in-four-places)).
- **Change the docs describing any behaviour you change, in the same PR.** The mapping is
  in [`docs/README.md`](docs/README.md#keeping-these-honest).
- **Never weaken a gate to make it pass.** CI is the gate; the Husky hooks are a local
  convenience.
