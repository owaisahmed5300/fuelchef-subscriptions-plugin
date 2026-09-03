# Architecture

How the repository is organised, how the plugin loads its code, and how dependencies are
isolated for release.

## The two-tree split

- **`plugin/`** is the only thing a release contains. Its `composer.json` declares the
  plugin's **runtime** dependencies and nothing else.
- **The root** holds development tooling, and its `composer.json` installs all of it. The
  root autoloader also maps `FuelChef\Subscriptions\ => plugin/src/`, so tests and analysis
  find plugin classes without the plugin's own `vendor/` existing.

That is why there are two `composer.json` files. You install the root one during
development; the plugin one is installed only as part of scoping.

## The boot sequence

`plugin/fuelchef-subscriptions.php` does four things, in order:

1. Defines the plugin constants.
2. `require_once`s `src/Requirements.php` and declares what the plugin needs.
3. Returns early if anything is unmet — the gate has already hooked the admin notice and
   the activation guard, so there is nothing else to do.
4. Loads the scoped autoloader, shares the gate into the container, and asks for
   `Plugin`.

Read the file itself for the detail; it is short, commented for the choices you will make
when you personalise it, and the only copy that cannot go stale.

**Both that file and `src/Requirements.php` must parse on PHP 5.3.** PHP parses an entire
file before executing any of it, so one arrow function anywhere in either is a fatal error
on old PHP and the gate never runs to explain why. That is why neither carries
`declare(strict_types=1)`, type hints, or closure shorthand. Two checks hold them to it:
[`standards.md`](standards.md#the-pre-flight-files-target-php-53-verified-under-php-56).

### The requirements gate

`plugin/src/Requirements.php` answers "may this plugin run?" — PHP version, WordPress
version, required plugins, and whether the scoped autoloader was shipped. When anything is
unmet it registers an admin notice and a `register_activation_hook` callback that stops
activation with `wp_die()`.

**Anything you do not call is not checked**, so a freshly generated plugin activates
anywhere. Add the checks you need as you take on dependencies.

Two properties are easy to break:

- **It is `require_once`d, not autoloaded.** Whether the autoloader exists is one of the
  things it checks, so it can depend on no other class.
- **Its messages are built at render time**, never at check time, and the plugin name and
  text domain are hardcoded — it runs before anything could inject them, and before the
  text domain loads, so failures render in the source language.

#### Choosing your minimums

`require_wp()` and `Requires at least:` must agree. But if you also `require_plugin()`
something, **that plugin's own WordPress minimum sets the floor, not you.** WooCommerce,
for instance, raises its own roughly every other release:

| WooCommerce | Requires WordPress |
|-------------|--------------------|
| 9.4         | 6.5                |
| 9.8         | 6.6                |
| 10.0        | 6.7                |
| 11.0        | 6.9                |

Declaring a WordPress version below what your required plugin needs describes a site
nobody can build: anyone able to install the dependency is already above your floor. Pick
the dependency's version first, read its `Requires at least:`, and take your WordPress
minimum from there.

### Which autoloader loads

Candidates are tried in order: `vendor-prefixed/scoper-autoload.php`, then
`vendor-prefixed/autoload.php`. php-scoper writes the first only when `scoper.inc.php`
exposes symbols — it exposes none by default, so the fallback is what loads. Preferring the
specific file means turning on any `expose-*` option cannot silently half-load the tree.

There is deliberately **no** fallback to an unscoped `plugin/vendor/autoload.php`, and
none to the root development autoloader. A checkout that has not run `scripts/scope`
should say so rather than half-work. Run `./scripts/dev plugin install` once after
cloning.

> The **test suite does not go through this path.** Its bootstrap loads the root Composer
> autoloader directly, so tests run with no scoped tree present.

## Dependency scoping

WordPress loads every active plugin into one PHP process, so two plugins bundling the same
library at different versions will break each other — whichever loads first wins.
[php-scoper](https://github.com/humbug/php-scoper) prevents that by rewriting your
dependencies into a private namespace, `FuelChef\Subscriptions\Dependencies\`.

That is why plugin code imports the prefixed name. **The unprefixed one resolves against
the dev tree and then fatals in a release.**

`scripts/scope` builds `plugin/vendor-prefixed/` and always produces it, whether or not
you have runtime dependencies yet:

- **With dependencies** — installs `plugin/composer.json` into `plugin/vendor/`, runs
  `php-scoper add-prefix` over it, regenerates an optimised autoloader across the prefixed
  tree, and deletes the unscoped copy.
- **With none** — there is nothing to prefix, so Composer's own autoloader (which already
  maps your namespace) is moved to `plugin/vendor-prefixed/`. Its paths are
  `__DIR__`-relative, so it keeps working after the move. This is the branch a freshly
  generated plugin takes, and it is why the boot path never needs a special case.

It uses `composer update` rather than `install` because `plugin/composer.lock` is a
gitignored build artifact, so a stale lock can never break the build.

### Adding a runtime dependency

Runtime dependencies go in `plugin/composer.json`, development tooling in the root one.
`./scripts/dev plugin <command>` runs Composer against the plugin manifest and re-scopes
afterwards, so the prefixed tree stays in sync:

```sh
./scripts/dev plugin require guzzlehttp/guzzle   # adds it and re-scopes in one step
```

`plugin/composer.json` pins `config.platform.php` to the plugin's PHP floor. Keep it: the
release build runs on a newer PHP, and without the pin Composer could resolve a dependency
that needs a PHP newer than the plugin claims to support.

### Why php-scoper is a PHAR, not a Composer dependency

It requires a `nikic/php-parser` version that conflicts with PHPStan's. Installing both in
one tree breaks static analysis, so php-scoper is consumed as a pinned PHAR — baked into
the tools image, downloaded in the release workflow — and kept out of `composer.json`.

## Development vs release, side by side

|                             | Development checkout                                | Test suite                    | Released zip                                        |
|-----------------------------|-----------------------------------------------------|-------------------------------|-----------------------------------------------------|
| Autoloader                  | `plugin/vendor-prefixed/autoload.php`               | root `vendor/autoload.php`    | `plugin/vendor-prefixed/autoload.php`               |
| Plugin classes from         | `plugin/src/`                                       | `plugin/src/`                 | `plugin/src/`                                       |
| Dependencies                | scoped under `FuelChef\Subscriptions\Dependencies\` | not loaded (mocked or unused) | scoped under `FuelChef\Subscriptions\Dependencies\` |
| Composer manifests          | present                                             | present                       | stripped from zip                                   |
| Needs `scripts/scope` first | yes                                                 | no                            | built by `release.yml`                              |
