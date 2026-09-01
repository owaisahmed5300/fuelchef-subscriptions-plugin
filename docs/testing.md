# Testing

How the suite is wired. What makes a test worth keeping is
[`guidelines/03-testing.md`](guidelines/03-testing.md); where a file goes is the
[tests README](../tests/README.md).

```sh
./scripts/dev test
```

## How the suite is wired

- `tests/Unit/`, config `phpunit.xml.dist`, bootstrap `tests/bootstrap/unit.php`.
- Test cases extend `WPPluginBoilerplate\Tests\TestCase`, which is the whole framework:
  it calls `Monkey\setUp()` / `Monkey\tearDown()` and uses Mockery's PHPUnit adapter, so an
  unmet expectation fails the test rather than passing silently.
- **No WordPress is loaded.** WordPress functions are mocked with
  [Brain Monkey](https://github.com/Brain-WP/BrainMonkey) — `Actions\expectAdded('init')`
  asserts a hook was registered without a running WordPress.
- The bootstrap defines `ABSPATH` and the plugin path constants before loading the
  autoloader. Plugin files exit when `ABSPATH` is undefined, so without it nothing loads at
  all. No test asserts on those values.
- The whole suite runs in about two seconds. No database, no fixtures, no setup.

## Conventions

| | |
| --- | --- |
| Directory | `tests/Unit/`, mirroring `plugin/src/` |
| Namespace | `WPPluginBoilerplate\Tests\Unit` |
| Base class | `WPPluginBoilerplate\Tests\TestCase` |

- **Naming follows the plugin's `Snake_Case` class style**, not PSR-4 studly caps:
  `Requirements` is tested by `Requirements_Test`.
- Test classes are `final` and carry `@covers \Fully\Qualified\ClassName`.
- Test methods are `test_descriptive_snake_case`, return `void`, and are named after the
  behaviour (`test_fails_when_wordpress_version_is_below_minimum`), not the method
  (`test_failures`).
- Always `declare(strict_types=1)`, and indent with tabs like the rest of the codebase.
- Shared stubs for a module go in an abstract `<Module>_TestCase.php` beside the tests —
  see `tests/Unit/Requirements_TestCase.php`, which models the site as properties rather
  than repeating stubs in every test.

## The PHPUnit 9.6 ceiling

`phpunit/phpunit` stays at `^9.6` because **the plugin supports PHP 8.0** and PHPUnit 10
requires 8.1 or later. The matrix runs on 8.0, so a newer PHPUnit could not install there.
Raising it means dropping PHP 8.0 — nothing else holds it back.
