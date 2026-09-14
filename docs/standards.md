# Coding standards & static analysis

What the linters enforce, how they are configured, and the few settings that are easy to
break. For what to write when a linter cannot help, see
[`guidelines/04-code-quality.md`](guidelines/04-code-quality.md).

```sh
./scripts/dev phpcbf     # auto-fix what can be fixed
./scripts/dev phpcs      # coding standards
./scripts/dev phpstan    # static analysis
```

## PHPCS

`phpcs.xml.dist` combines two rulesets from `wptechnix/wp-coding-standards`:

- **`WPTechnix-PSR4`** — WordPress core style with PSR-4 file names and short array
  syntax (`[]`).
- **`WPTechnix-Strict`** — modern-PHP quality sniffs on top.

It scans `plugin/` only. `tests/` is excluded entirely: a test method's name is its own
documentation and test style is covered by [`03-testing.md`](guidelines/03-testing.md),
not by the linters that hold `plugin/` to PHPCS/PHPStan-level-10 strictness. The text
domain is `fuelchef-subscriptions` and must match the `Text Domain:` header in the main
plugin file, or the i18n sniffs report every translated string.

### The exclusions, and why each exists

| Excluded                                                                                                                       | Where                    | Why                                                                                                                          |
|------------------------------------------------------------------------------------------------------------------------------------|--------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| `SlevomatCodingStandard.TypeHints.*`, `Generic.PHP.RequireStrictTypes`, `SlevomatCodingStandard.Functions.StaticClosure`, `Generic.Arrays.DisallowLongArraySyntax` | The two pre-flight files | Each forces a feature newer than PHP 5.3 (type hints, `strict_types`, static closures, `[]`) — see [below](#the-pre-flight-files-target-php-53-verified-under-php-56) |

Add an exclusion only when the sniff is wrong for that file, never to silence a finding
you could fix. Each one above names the file it applies to rather than switching the
sniff off globally.

### Typed properties carry no `@var`

Two `severity 0` overrides in `phpcs.xml.dist` suppress this on purpose: the native type
is self-documenting. Without them the base standard deadlocks — WordPress-Docs demands a
`@var`, Slevomat forbids one that merely repeats the type, and no file can satisfy both.
Read the comment there before touching either override.

## PHPStan

`phpstan.neon.dist` runs at **level 10** plus `phpstan-strict-rules`, with WordPress and
WooCommerce stubs. It analyses `plugin/src`, `plugin/uninstall.php` and the main plugin
file — never `tests/`.

- **It scans `plugin/vendor-prefixed/`**, which is gitignored. Run
  `./scripts/dev plugin install` before analysing a fresh checkout, or every scoped class
  reports as unknown.
- **`phpVersion` is a range**, `min: 80000` to `max: 80500`, so a construct that is valid
  on 8.5 but broken on 8.0 is caught here rather than by the CI matrix. PHPStan requires
  both bounds when you use the range form.
- **`bootstrapFiles: tests/bootstrap/phpstan.php`** declares the two plugin constants
  PHPStan cannot work out for itself. It reads the others straight from their `define()`
  calls in the main plugin file, but `_DIR` and `_URL` are built by WordPress functions at
  runtime.
- **`dynamicConstantNames` takes a list, not a map.** It marks a *known* constant's value
  as unknown; it does not declare that the constant exists, which is why the bootstrap
  above cannot go away. Written as `NAME: type` it silently declares a constant called
  `type` and does nothing.

Never widen a type or add a `@phpstan-ignore` to silence an error. If an ignore is
genuinely right it goes in `phpstan.neon.dist`'s `ignoreErrors`, with a comment saying
why.

## The PHP floor is declared in four places

Change one and you must change all four, or the tools disagree about what is legal:

| Where                               | Setting                                 |
|-------------------------------------|-----------------------------------------|
| `plugin/fuelchef-subscriptions.php` | `Requires PHP:` header                  |
| `plugin/composer.json`              | `require.php` and `config.platform.php` |
| `phpcs.xml.dist`                    | `testVersion`                           |
| `phpstan.neon.dist`                 | `phpVersion.min`                        |

The committed `composer.lock` must also stay installable on that floor — the unit matrix
installs it on the lowest supported version. `config.platform.php` in the root
`composer.json` makes Composer enforce this, so do not remove it to resolve a conflict.

## The pre-flight files target PHP 5.3, verified under PHP 5.6

`plugin/src/Requirements.php` and the main plugin file both run *before* the plugin's PHP
version has been checked. [`architecture.md`](architecture.md#the-requirements-gate)
explains why that matters; this is how it is held.

The files themselves stay **PHP 5.3 compatible** for maximum reach — anyone on PHP 5.3
must still get a clean "your PHP is too old" message rather than a white screen. The tools
that *verify* that run an interpreter old enough to prove it, but not so old it no longer
exists in CI: the `legacy-parse` job and the Docker commands below use **PHP 5.6**, the
oldest version `shivammathur/setup-php` and the official Docker image still make
available anywhere. 5.6 parses everything 5.3 can, so passing it still proves the files
avoid modern syntax; the PHPCompatibility check below is what guards 5.3 *semantics*.

Two checks cover it, and the difference between them matters:

- **`composer lint:compat`** runs PHPCompatibility, which catches *semantic* problems: a
  function that did not exist yet, behaviour that was removed. It does **not** catch every
  modern syntax construct — the pinned 9.3.5 release predates arrow functions and cannot
  see `fn()` at all. Its `testVersion` stays `5.3-`, holding the files to their PHP 5.3
  target.
- **The `legacy-parse` CI job** lints both files with a real old PHP — currently 5.6, the
  oldest interpreter still reliably installable on GitHub-hosted runners (PHP 5.3 has been
  dropped by `shivammathur/setup-php` on modern Ubuntus and the official `php:5.3` Docker
  images are abandoned, built from dead download URLs). 5.6 parses every construct 5.3
  does, so it still proves the files hold no modern syntax — the check with no blind spots.
  Locally — the wrapper is the local twin of the CI job; the raw Docker commands do the
  same thing by hand:

  ```sh
  ./scripts/dev preflight
  ```

  ```sh
  docker run --rm -v "$PWD:/app" -w /app php:5.6-cli php -l plugin/src/Requirements.php
  docker run --rm -v "$PWD:/app" -w /app php:5.6-cli php -l plugin/fuelchef-subscriptions.php
  ```

## EditorConfig

`.editorconfig` owns whitespace and is short enough to read; it explains each block
inline. There is no local checker, so CI is the only thing enforcing it — and it runs
`editorconfig-checker` with two checks switched off:

- `-disable-max-line-length`, because PHPCS owns line length and understands PHP context.
- `-disable-indent-size`, because it fires on markdown list continuations, shell heredoc
  bodies and embedded awk, none of which are wrong.

Both are CLI flags in `lint.yml` rather than config, so the reason sits beside the flag.

## Local overrides

Shared configs are committed as `*.dist`. Each tool looks for the non-`.dist` name first,
so a local `phpcs.xml`, `phpstan.neon` or `phpunit.xml` overrides the shared config
without touching version control. Those bare names are gitignored.
